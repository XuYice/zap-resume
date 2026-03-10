<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AiTask;

class TaskDashboard extends Component
{
    public function render()
    {
        $tasks = AiTask::with(['user', 'whatsappMessage']) // <--- 预加载关系！
            ->where('status', 'pending')
            ->orderByRaw("FIELD(priority_level, 'high', 'normal', 'low')")
            ->latest()
            ->get();

        return view('livewire.task-dashboard', [
            'tasks' => $tasks
        ]);
    }

    public function markAsDone($taskId)
    {
        $task = AiTask::find($taskId);
        if ($task) {
            $task->status = 'done';
            $task->save();
        }
    }
}
