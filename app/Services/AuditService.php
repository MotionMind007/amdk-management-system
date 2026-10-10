<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    /** @param array<string, mixed>|null $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        Request $request,
        string $action,
        string $module,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'module' => $module,
            'description' => $description ?? $this->makeDescription($action, $module, $entity, $newValues),
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /** @param array<string, mixed>|null $newValues */
    private function makeDescription(string $action, string $module, ?Model $entity, ?array $newValues): string
    {
        $identifier = $entity?->getAttribute('number')
            ?? $entity?->getAttribute('name')
            ?? $entity?->getAttribute('reference_number')
            ?? data_get($newValues, 'number')
            ?? data_get($newValues, 'name')
            ?? data_get($newValues, 'reference_number');

        $description = AuditLog::actionLabelFor($action).' '.AuditLog::moduleLabelFor($module);

        if (filled($identifier)) {
            $description .= " {$identifier}";
        }

        $amount = data_get($newValues, 'amount') ?? data_get($newValues, 'total');
        if (is_numeric($amount) && (float) $amount > 0) {
            $description .= ' senilai Rp '.number_format((float) $amount, 0, ',', '.');
        }

        $notes = data_get($newValues, 'description') ?? data_get($newValues, 'notes');
        if (filled($notes)) {
            $description .= ". Keterangan: {$notes}";
        }

        return $description.'.';
    }
}
