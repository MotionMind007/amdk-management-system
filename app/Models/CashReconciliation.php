<?php

namespace App\Models;

use Database\Factories\CashReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['number', 'cash_account_id', 'reconciliation_date', 'system_balance', 'statement_balance', 'difference', 'notes', 'created_by'])]
class CashReconciliation extends Model
{
    /** @use HasFactory<CashReconciliationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['reconciliation_date' => 'date', 'system_balance' => 'decimal:2', 'statement_balance' => 'decimal:2', 'difference' => 'decimal:2'];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
