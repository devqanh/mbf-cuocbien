<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TaskUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string,array{0:mixed,1:mixed}> $changes  Map field => [from, to]
     */
    public function __construct(
        public Task $task,
        public User $editor,
        public array $changes = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        // Tóm tắt thay đổi cho thân thiện
        $summary = [];
        foreach ($this->changes as $field => [$from, $to]) {
            $summary[] = match ($field) {
                'status'   => "trạng thái → {$to}",
                'priority' => "độ ưu tiên → {$to}",
                'due_at'   => $to ? 'cập nhật hạn' : 'gỡ hạn',
                'title'    => 'đổi tiêu đề',
                'body'     => 'cập nhật nội dung',
                default    => "đổi {$field}",
            };
        }
        $summaryText = implode(', ', $summary) ?: 'cập nhật task';

        return [
            'type'        => 'task.updated',
            'task_id'     => $this->task->id,
            'title'       => $this->task->title,
            'editor_id'   => $this->editor->id,
            'editor'      => $this->editor->name,
            'changes'     => array_keys($this->changes),
            'url'         => route('tasks.show', $this->task),
            'message'     => sprintf('%s %s: %s', $this->editor->name, $summaryText, $this->task->title),
            'icon'        => 'pencil-square',
            'color'       => 'info',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
