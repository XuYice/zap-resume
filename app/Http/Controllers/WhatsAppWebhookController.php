<?php


namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Services\WhatsAppAiService;
use App\Models\WhatsappMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;


class WhatsAppWebhookController extends Controller
{
    // 处理 WhatsApp Webhook 请求的主方法
    public function handle(Request $request, WhatsAppAiService $aiService)
    {
        try {
            // 获取第一个用户（实际应用中可根据业务调整）
            $user = User::first();

            // 从请求中获取消息文本内容，默认为空字符串
            $rawContent = $request->input('text', '');
            // 从请求中获取发送者手机号，默认为 unknown
            $senderPhone = $request->input('from', 'unknown');

            // 如果消息内容为空，则直接返回忽略状态
            if (empty($rawContent)) {
                return response()->json(['status' => 'ignored'], 200);
            }

            // 创建一条新的 WhatsApp 消息记录
            $message = WhatsappMessage::create([
                'user_id'      => $user->id, // 关联用户 ID
                'sender_phone' => $senderPhone, // 记录发送者手机号
                'raw_content'  => $rawContent, // 原始消息内容
                'is_processed' => false, // 标记为未处理
                'received_at'  => now(), // 记录接收时间
            ]);

            // 调用 AI 服务处理消息内容，自动提取待办任务
            $aiService->processMessage($message, $user);

            // 返回处理成功的响应
            return response()->json(['status' => 'success'], 200);
        }catch (\Exception $e) {
            // 捕获异常并记录错误日志
            Log::error('Webhook failed: ' . $e->getMessage());
            // 返回错误状态响应
            return response()->json(['status' => 'error'], 200);
        }
    }
}
