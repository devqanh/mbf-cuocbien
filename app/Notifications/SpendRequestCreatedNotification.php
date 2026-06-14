<?php

namespace App\Notifications;

use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleCost;
use App\Support\Hashid;
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
        $amount  = (int) round((float) $this->cost->amount);
        $isAsset = ($this->vehicle->kind ?? 'vehicle') === 'asset';
        $vinfo   = is_array($this->vehicle->info) ? $this->vehicle->info : [];
        $target  = $isAsset ? (($vinfo['name'] ?? '') ?: $this->vehicle->plate) : $this->vehicle->plate;

        return [
            'type'       => 'trucking.spend_request',
            'cost_id'    => $this->cost->id,
            'vehicle_id' => $this->vehicle->id,
            'kind'       => $isAsset ? 'asset' : 'vehicle',
            'plate'      => $this->vehicle->plate,
            'target'     => $target,
            'item'       => $this->cost->name,
            'invoice_no' => $this->cost->invoice_no,
            'amount'     => $amount,
            // Deep-link: mở đúng chế độ (xe / tài sản) + tab chi phí + cuộn tới phiếu. Dùng hashid (không lộ id).
            'url'        => route('trucking2.fleet') . '#' . ($isAsset ? 'asset/' : '') . Hashid::encode($this->vehicle->id) . '/cost/' . Hashid::encode($this->cost->id),
            'message'    => sprintf('Yêu cầu chi “%s” cho %s %s — %s đ. Chờ duyệt.',
                $this->cost->name,
                $isAsset ? 'tài sản' : 'xe',
                $target,
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
