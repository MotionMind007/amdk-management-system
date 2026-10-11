<?php

namespace App\Services;

use App\Models\SubmissionToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SubmissionTokenService
{
    public function claim(?string $token, User $user, string $action, ?Model $subject = null): void
    {
        if ($token === null) {
            return;
        }

        try {
            SubmissionToken::query()->create([
                'token' => $token,
                'user_id' => $user->id,
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['submission_token' => 'Transaksi ini sudah diproses. Muat ulang halaman sebelum mencoba kembali.']);
        }
    }
}
