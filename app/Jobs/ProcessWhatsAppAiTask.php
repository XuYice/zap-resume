<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
// 命名空间，定义该 Job 属于 App\Jobs
use App\Models\User;
use App\Services\WhatsAppAiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppAiTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // 最大尝试次数

    public $timeout = 120; // 超时时间（秒）

    public function __construct(
        public WhatsappMessage $message,
        public User $user
    ){}

    public function handle(WhatsAppAiService $aiService): void
    {
        $aiService->processMessage($this->message, $this->user);
    }
}
