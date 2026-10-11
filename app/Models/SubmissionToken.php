<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['token', 'user_id', 'action', 'subject_type', 'subject_id'])]
class SubmissionToken extends Model
{
    public $timestamps = true;
}
