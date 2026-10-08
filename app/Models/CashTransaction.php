<?php

namespace App\Models;

use App\ExpenseCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['cash_account_id', 'transaction_date', 'direction', 'expense_category', 'amount', 'source_type', 'source_id', 'reference_number', 'description', 'user_id'])]
class CashTransaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'expense_category' => ExpenseCategory::class, 'amount' => 'decimal:2', 'created_at' => 'datetime'];
    }
}
