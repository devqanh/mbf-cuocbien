<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskCommentedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public TaskComment $comment,
        public User $author,
        public bool $isMention = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $preview = Str::limit($this->comment->body, 80);

        return [
            'type'        => $this->isMention ? 'task.mentioned' : 'task.commented',
            'task_id'     => $this->task->id,
            'title'       => $this->task->title,
            'comment_id'  => $this->comment->id,
            'author_id'   => $this->author->id,
            'author'      => $this->author->name,
            'url'         => route('tasks.show', $this->task) . '#comment-' . $this->comment->id,
            'preview'     => $preview,
            'message'     => $this->isMention
                ? sprintf('%s nhắc đến bạn trong "%s": %s', $this->author->name, $this->task->title, $preview)
                : sprintf('%s bình luận "%s": %s', $this->author->name, $this->task->title, $preview),
            'icon'        => $this->isMention ? 'at' : 'chat-square-text-fill',
            'color'       => $this->isMention ? 'warning' : 'info',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
