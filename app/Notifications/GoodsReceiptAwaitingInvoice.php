<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class GoodsReceiptAwaitingInvoice extends Notification
{
    public function __construct(
        public readonly int $goodsReceiptId,
        public readonly string $receiptNumber,
        public readonly string $purchaseOrderNumber,
        public readonly string $supplierName,
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
        return 'goods-receipt-awaiting-invoice';
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Faktur Supplier Belum Dicatat',
            'message' => "Penerimaan {$this->receiptNumber} untuk {$this->purchaseOrderNumber} dari {$this->supplierName} sudah diposting. Catat faktur supplier untuk membentuk hutang.",
            'route' => 'purchasing.index',
            'action_label' => 'Catat Faktur',
            'category' => 'supplier_invoice',
            'goods_receipt_id' => $this->goodsReceiptId,
        ];
    }
}
