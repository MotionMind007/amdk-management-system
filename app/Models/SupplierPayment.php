<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['number', 'purchase_order_id', 'cash_account_id', 'payment_date', 'amount', 'notes', 'created_by'])]
class SupplierPayment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }
}
