<div class="min-h-screen bg-gray-100 py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-md p-6">
        <h1 class="text-2xl font-black text-gray-800 mb-6 border-b pb-4">
            🤖 待办任务控制台 (AI 提取)
        </h1>

        <div class="space-y-4">
            @forelse($tasks as $task)
                <div class="p-4 border-l-4 rounded-r-lg shadow-sm flex justify-between items-center bg-white
                    {{ $task->priority_level === 'high' ? 'border-red-500 bg-red-50' : 'border-gray-300' }}">

                    <div>
                        <p class="text-lg {{ $task->priority_level === 'high' ? 'text-red-700 font-bold' : 'text-gray-800' }}">
                            {{ $task->description }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            来自 WhatsApp · {{ $task->user->name }} · {{ $task->whatsappMessage->sender_phone }} · {{ $task->created_at->diffForHumans() }}
                        </p>
                    </div>

                    @if($task->priority_level === 'high')
                        <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full animate-pulse">
                            紧急 / HIGH
                        </span>
                    @else
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-xs font-semibold rounded-full">
                            普通 / NORMAL
                        </span>
                    @endif

                    <button
                        wire:click="markAsDone({{ $task->id }})"
                        class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full border-2 border-gray-300 text-gray-300 hover:border-green-500 hover:text-green-500 hover:bg-green-50 transition-all duration-200 focus:outline-none"
                        title="Concluir Tarefa"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </button>
                </div>
            @empty
                <div class="text-center py-10 text-gray-400">
                    <svg class="mx-auto h-12 w-12 mb-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    太棒了！目前没有任何待办任务。
                </div>
            @endforelse
        </div>
    </div>
</div>
