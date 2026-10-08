<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cash_account_id', 'transaction_date', 'direction', 'amount', 'source_type', 'source_id', 'reference_number', 'description', 'user_id'])]
class CashTransaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'amount' => 'decimal:2', 'created_at' => 'datetime'];
    }
}
