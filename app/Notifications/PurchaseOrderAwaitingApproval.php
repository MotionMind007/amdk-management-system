<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PurchaseOrderAwaitingApproval extends Notification
{
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly string $number,
        public readonly string $supplierName,
        public readonly string $actorName,
        public readonly string $total,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'purchase-order-awaiting-approval';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'PO Baru Menunggu Persetujuan',
            'message' => "{$this->number} dari {$this->supplierName} senilai Rp ".number_format((float) $this->total, 0, ',', '.')." dibuat oleh {$this->actorName}.",
            'route' => 'purchasing.index',
            'action_label' => 'Tinjau PO',
            'category' => 'purchase_order',
            'purchase_order_id' => $this->purchaseOrderId,
        ];
    }
}
