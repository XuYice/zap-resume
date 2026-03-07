<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AiTask;

class TaskDashboard extends Component
{
    public function render()
    {
        // Usamos o CASE WHEN que funciona perfeitamente no SQLite!
        $tasks = AiTask::where('status', 'pending')
            ->orderByRaw("
                CASE priority_level
                    WHEN 'high' THEN 1
                    WHEN 'normal' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 4
                END
            ")
            ->latest()
            ->get();

        return view('livewire.task-dashboard', [
            'tasks' => $tasks
        ])->layout('layouts.app');
    }
}
