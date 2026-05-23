<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification debug — admin bấm nút "Test broadcast" trên /users để mọi user kiểm tra
 * Reverb có hoạt động không. Lưu DB + broadcast realtime.
 */
class BroadcastTestNotification extends Notification
{
    use Queueable;

    public function __construct(public User $sender) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function broadcastType(): string
    {
        return 'system.broadcast_test';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'system.broadcast_test',
            'sender_id' => $this->sender->id,
            'sender'    => $this->sender->name,
            'sent_at'   => now()->format('H:i:s'),
            'message'   => sprintf('%s vừa gửi 1 thông báo TEST lúc %s — Reverb đang hoạt động!',
                                   $this->sender->name, now()->format('H:i:s')),
            'url'       => route('users.index'),
            'icon'      => 'broadcast',
            'color'     => 'success',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
