<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SaleAwaitingDelivery extends Notification
{
    public function __construct(
        public readonly int $saleId,
        public readonly string $number,
        public readonly string $customerName,
        public readonly string $warehouseName,
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
        return 'sale-awaiting-delivery';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Penjualan Menunggu Pengiriman',
            'message' => "{$this->number} untuk {$this->customerName} siap dikirim dari {$this->warehouseName}.",
            'route' => 'sales.deliveries.index',
            'action_label' => 'Proses Pengiriman',
            'category' => 'delivery',
            'sale_id' => $this->saleId,
        ];
    }
}
