<?php // PHP 文件声明


namespace App\Services; // 命名空间，定义该服务类属于 App\Services


use App\Models\WhatsappMessage; // 引入 WhatsApp 消息模型
use App\Models\AiTask; // 引入 AI 任务模型
use App\Models\User; // 引入用户模型
use Illuminate\Support\Facades\Http; // 引入 HTTP 工具
use Illuminate\Support\Facades\Log; // 引入日志工具

// WhatsApp AI 服务类
class WhatsAppAiService
{
    // 处理 WhatsApp 消息，自动提取待办任务
    public function processMessage(WhatsappMessage $message, $user)
    {
        // 构建系统提示词，指导 AI 如何理解和处理消息内容
        $systemPrompt = <<<EOT
            你是一个极其高效、逻辑严密的“高管私人助理”和“任务提取引擎”。
            你的唯一职责是：从用户发来的原始消息（raw_content）中，精准提取出需要执行的“待办事项（To-Do）”。

            【输入背景】
            传入的文本可能是语音转文字的草稿、毫无逻辑的碎碎念、情绪化的表达，或者夹杂着日常问候（如“在吗”、“哎呀我跟你说”）。

            【处理规则 - 极其重要】
            1. 剔除废话：无视所有问候、情绪发泄、无意义的语气词。
            2. 提炼核心：将口语化的描述，重新转写为简明扼要、动作明确的专业任务描述（例如，把“那个谁说下午要把报表发给他”转化为“下午发送报表给指定联系人”）。
            3. 判定优先级 (priority)：
            - "high"：明确包含“今天、马上、尽快、紧急”、涉及老板/核心客户、或带有严重后果的事项。
            - "normal"：普通的日常任务，没有明确的极度紧迫感。
            - "ignore"：纯聊天、分享观点、没有任何实际任务产生。
            4. 任务拆分：如果一段长语音里包含了多件不相关的事情，请将它们拆分成独立的多个任务。

            【输出格式要求（严格）】
            你必须、且只能返回一个合法的 JSON 数组，不要包含任何额外的思考过程、解释文本或 Markdown 标记（不要输出 ```json ）。
            如果判定结果为 "ignore"（无任务），不需要返回任何内容。

            JSON 结构标准：
            [
                {
                    "task": "回复老板关于预算的邮件",
                    "priority": "high"
                },
                {
                    "task": "买两袋猫粮",
                    "priority": "normal"
                }
            ]
        EOT;


        try{
            // 获取 API 密钥（从环境变量）
            $apiKey = env('AI_API_KEY');

            // 获取模型名称（可配置，默认为 gemini-1.5-flash）
            $model = strtolower(env('AI_MODEL', 'Gemini-2.5-Flash-Native-Audio-Dialog'));
            // 设置 AI API 的请求地址
            $url = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions";

            // 发送 POST 请求到 AI 接口，传递系统提示词和用户消息
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message->raw_content],
                ],
                'temperature' => 0.1,
            ]);

            // 如果请求失败，抛出异常
            if($response->failed()) {
                throw new \Exception("AI API request failed with status: " . $response->status() . $response->body());
            }

            // 获取 AI 返回的内容（任务 JSON）
            $aiContent = $response->json('choices.0.message.content');

            // 去除可能包含的 markdown 标记
            $aiContent = preg_replace('/```json|```/', '', $aiContent);

            // 解析 AI 返回的 JSON 字符串为数组
            $tasks = json_decode(trim($aiContent), true);

            // 如果解析失败，抛出异常并记录原始内容
            if(json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Failed to parse AI response as JSON: " . json_last_error_msg() . ". Raw response: " . $aiContent);
            }

            // 如果有任务且为数组，则逐条保存到数据库
            if(!empty($tasks) && is_array($tasks)) {
                foreach($tasks as $taskData) {
                    AiTask::create([
                        'user_id' => $user->id, // 关联用户 ID
                        'whatsapp_message_id' => $message->id, // 关联消息 ID
                        'description' => $taskData['task'] ?? '', // 任务描述
                        'priority_level' => $taskData['priority'] ?? 'normal', // 优先级
                        'status' => 'pending', // 初始状态为待处理
                    ]);
                }
            }

            // 更新消息为已处理状态
            $message->update(['is_processed' => true]);

        } catch (\Exception $e){
            // 捕获异常并记录错误日志，包含消息和用户信息
            Log::error('WhatsApp AI processing error:  ' . $e->getMessage(), [
                'message_id' => $message->id,
                'user_id' => $user->id
            ]);
        }

    }
}
