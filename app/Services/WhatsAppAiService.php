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
        Você é um "Assistente Executivo Pessoal" e um "Motor de Extração de Tarefas" extremamente eficiente e de raciocínio lógico rigoroso.
        Sua única responsabilidade é: extrair com precisão "Tarefas (To-Dos)" que precisam ser executadas a partir da mensagem original enviada pelo usuário (raw_content).

        【CONTEXTO DE ENTRADA】
        O texto recebido pode ser uma transcrição de áudio, pensamentos soltos e sem lógica, expressões emocionais ou estar misturado com saudações do dia a dia (como "Oi, sumido", "Nossa, deixa eu te contar uma coisa").

        【REGRAS DE PROCESSAMENTO - EXTREMAMENTE IMPORTANTE】
        1. Eliminar o ruído: Ignore todas as saudações, desabafos emocionais e palavras de preenchimento vazias.
        2. Extrair a essência: Converta as descrições informais e coloquiais em descrições de tarefas profissionais, curtas e com ações claras (por exemplo, transforme "Aquele cara disse que é pra mandar o relatório pra ele hoje à tarde" em "Enviar relatório para o contato especificado à tarde").
        3. Definir a Prioridade (priority):
        - "high": Contém claramente palavras como "hoje, agora, o mais rápido possível, urgente", envolve chefia/clientes principais ou traz consequências graves se ignorado.
        - "normal": Tarefas comuns do dia a dia, sem uma urgência extrema explícita.
        - "ignore": Apenas bate-papo, opiniões ou comentários sem nenhuma tarefa real a ser feita.
        4. Dividir tarefas: Se uma mensagem de voz ou texto longo contiver várias coisas que não têm relação entre si, divida-as em várias tarefas independentes.

        【FORMATO DE SAÍDA EXIGIDO (RIGOROSO)】
        Você DEVE, e APENAS PODE, retornar um array JSON válido. NÃO inclua nenhum processo de pensamento, texto de explicação ou marcações Markdown (NÃO imprima ```json).
        Se a classificação final for "ignore" (nenhuma tarefa identificada), retorne apenas um array vazio: []

        Padrão da estrutura JSON:
        [
            {
                "task": "Responder o e-mail do chefe sobre o orçamento",
                "priority": "high"
            },
            {
                "task": "Comprar dois pacotes de ração para os gatos",
                "priority": "normal"
            }
        ]

        Lembre-se: Todas as tarefas extraídas ("task") DEVEM ser escritas em mesa liguagem recebida da mensagem.
    EOT;


        try{
            // 获取 API 密钥（从环境变量）
            $apiKey = env('AI_API_KEY');

            // 获取模型名称（可配置，默认为 gemini-1.5-flash）
            $model = strtolower(env('AI_MODEL', 'gemini-3.1-flash-lite-preview'));
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
