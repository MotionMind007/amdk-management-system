<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class GoodsReceiptExpected extends Notification
{
    public function __construct(
        public readonly int $purchaseOrderId,
        public readonly string $number,
        public readonly string $supplierName,
        public readonly string $approverName,
        public readonly ?string $expectedDate,
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
        return 'goods-receipt-expected';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $arrivalInformation = $this->expectedDate === null
            ? 'Tanggal kedatangan belum ditentukan.'
            : "Perkiraan tiba {$this->expectedDate}.";

        return [
            'title' => 'Barang Masuk Menunggu Penerimaan',
            'message' => "{$this->number} dari {$this->supplierName} telah disetujui oleh {$this->approverName}. {$arrivalInformation}",
            'route' => 'purchasing.index',
            'action_label' => 'Proses Penerimaan',
            'category' => 'goods_receipt',
            'purchase_order_id' => $this->purchaseOrderId,
        ];
    }
}
