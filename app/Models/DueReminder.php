<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['reminder_date', 'kind', 'subject_type', 'subject_id'])]
class DueReminder extends Model
{
    protected function casts(): array
    {
        return ['reminder_date' => 'date'];
    }
}
