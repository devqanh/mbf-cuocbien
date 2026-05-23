<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assigner,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'task.assigned',
            'task_id'      => $this->task->id,
            'title'        => $this->task->title,
            'priority'     => $this->task->priority,
            'due_at'       => $this->task->due_at?->toIso8601String(),
            'assigner_id'  => $this->assigner->id,
            'assigner'     => $this->assigner->name,
            'url'          => route('tasks.show', $this->task),
            'message'      => sprintf('%s đã giao cho bạn task: %s',
                $this->assigner->name, $this->task->title),
            'icon'         => 'person-fill-add',
            'color'        => 'primary',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
