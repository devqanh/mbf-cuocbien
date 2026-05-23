<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $dueAt   = $this->task->due_at;
        $overdue = $dueAt && $dueAt->isPast();
        $rel     = $dueAt
            ? ($overdue ? 'đã quá hạn ' . $dueAt->diffForHumans(now(), true)
                        : 'còn ' . $dueAt->diffForHumans(now(), true))
            : '';

        return [
            'type'      => 'task.reminder',
            'task_id'   => $this->task->id,
            'title'     => $this->task->title,
            'priority'  => $this->task->priority,
            'due_at'    => $dueAt?->toIso8601String(),
            'overdue'   => $overdue,
            'url'       => route('tasks.show', $this->task),
            'message'   => sprintf('Nhắc hẹn: %s — %s', $this->task->title, $rel),
            'icon'      => $overdue ? 'alarm-fill' : 'bell-fill',
            'color'     => $overdue ? 'danger' : 'warning',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
