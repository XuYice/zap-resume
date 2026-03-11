<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppAiService;
use App\Models\WhatsappMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessWhatsAppAiTask;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppAiService $aiService)
    {
        try {
            // 1. 记录收到的 JSON 数据到日志（便于调试）
            Log::info('Webhook Evolution 接收到数据:', $request->all());

            // $user = User::first();

            // 2. 从 webhook 请求中提取 data 字段
            $data = $request->input('data');

            // 如果没有 data 字段，直接忽略
            if (!$data) {
                return response()->json(['status' => 'ignored_no_data'], 200);
            }

            // 获取 key 字段，包含发送者相关信息
            $key = data_get($data, 'key') ?? data_get($data, 'message.key');

            // 3. 黄金过滤：忽略自己/机器人发送的消息
            // $fromMe = data_get($key, 'fromMe', false);
            // if ($fromMe === true) {
            //     return response()->json(['status' => 'ignored_from_me', 'motivo' => '消息由机器人发送'], 200);
            // }

            $messageData = data_get($data, 'message') ?? $data;
            $senderPhone = data_get($key, 'remoteJid', 'unknown');
            $senderName = data_get($data, 'pushName', '新客户');

            // 4. 查找或创建用户（根据手机号）
            $user = User::firstOrCreate(
                ['phone' => $senderPhone], // 用手机号查找
                [
                    'name' => $senderName,    // 存入真实姓名
                    'email' => $senderPhone . '@whatsapp.com', // 虚拟邮箱（如数据库要求）
                    'password' => bcrypt(uniqid()) // 随机密码
                ]
            );
            // 4. 群聊过滤：如果消息来自群聊则忽略（如需可取消注释）
            // if (str_contains($senderPhone, '@g.us')) {
            //     return response()->json(['status' => 'ignored_group'], 200);
            // }

            // 5. 状态过滤：如果是状态更新消息则忽略
            if (str_contains($senderPhone, '@broadcast')) {
                return response()->json(['status' => 'ignored_status'], 200);
            }

            // 6. 获取消息文本内容
            $rawContent = data_get($messageData, 'conversation')
                        ?? data_get($messageData, 'extendedTextMessage.text')
                        ?? '';

            // 如果是语音、图片、表情等无文本内容，暂时忽略
            if (empty(trim($rawContent))) {
                return response()->json(['status' => 'ignored_empty_text'], 200);
            }

            // 通过所有过滤后，保存消息到数据库
            $message = WhatsappMessage::create([
                'user_id'      => $user->id, // 关联用户 ID
                'sender_phone' => $senderPhone, // 记录发送者手机号
                'raw_content'  => $rawContent, // 原始消息内容
                'is_processed' => false, // 标记为未处理
                'received_at'  => now(), // 记录接收时间
            ]);

            // 7. 推送到队列，交给 AI 处理
            ProcessWhatsAppAiTask::dispatch($message, $user);

            // 返回处理成功的响应
            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            // 捕获异常并记录错误日志
            Log::error('Webhook failed: ' . $e->getMessage());
            // 返回错误状态响应
            return response()->json(['status' => 'error'], 200);
        }
    }
}
