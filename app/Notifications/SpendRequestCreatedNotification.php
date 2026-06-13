<?php

namespace App\Notifications;

use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleCost;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Có yêu cầu chi tiền mới gửi từ trang public /yeu-cau-chi.
 * Gửi cho người duyệt chi (quyền settings.update). Click thông báo → mở thẳng
 * trang quản lý xe, vào tab chi phí và cuộn/highlight đúng phiếu chi vừa gửi
 * (deep-link qua hash #<vehicleId>/cost/<costId>).
 */
class SpendRequestCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TruckingVehicleCost $cost,
        public TruckingVehicle $vehicle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** Override broadcast event "type" để client dễ branch (mặc định Laravel dùng FQCN). */
    public function broadcastType(): string
    {
        return 'trucking.spend_request';
    }

    public function toArray(object $notifiable): array
    {
        $amount = (int) round((float) $this->cost->amount);

        return [
            'type'       => 'trucking.spend_request',
            'cost_id'    => $this->cost->id,
            'vehicle_id' => $this->vehicle->id,
            'plate'      => $this->vehicle->plate,
            'item'       => $this->cost->name,
            'invoice_no' => $this->cost->invoice_no,
            'amount'     => $amount,
            // Deep-link: mở xe + tab chi phí + cuộn tới đúng phiếu chi.
            'url'        => route('trucking2.fleet') . '#' . $this->vehicle->id . '/cost/' . $this->cost->id,
            'message'    => sprintf('Yêu cầu chi “%s” cho xe %s — %s đ. Chờ duyệt.',
                $this->cost->name,
                $this->vehicle->plate,
                number_format($amount, 0, ',', '.')),
            'icon'       => 'cash-coin',
            'color'      => 'warning',
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
