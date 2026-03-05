<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppAiService;
use App\Models\WhatsappMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, WhatsAppAiService $aiService)
    {
        try {
            $user = User::first();

            $rawContent = $request->input('text', '');
            $senderPhone = $request->input('from', 'unknown');

            if (empty($rawContent)) {
                return response()->json(['status' => 'ignored'], 200);
            }

            $message = WhatsappMessage::create([
                'user_id'      => $user->id,
                'sender_phone' => $senderPhone,
                'raw_content'  => $rawContent,
                'is_processed' => false,
                'received_at'  => now(),
            ]);

            $aiService->processMessage($message, $user);

            return response()->json(['status' => 'success'], 200);
        }catch (\Exception $e) {
            Log::error('Webhook failed: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 200);
        }
    }
}
