<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $prefix, ?int $year = null): string
    {
        $documentYear = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($prefix, $documentYear): string {
            DB::table('document_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'year' => $documentYear,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::query()
                ->where('prefix', $prefix)
                ->where('year', $documentYear)
                ->lockForUpdate()
                ->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('%s-%d-%06d', $prefix, $documentYear, $sequence->fresh()->last_number);
        });
    }
}
