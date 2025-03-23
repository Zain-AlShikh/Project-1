<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResetCodePassword extends Model
{
    // protected $table = 'rest_code_passwords';

    protected $fillable = [
        'email',
        'code',
        'created_at',
    ];
}
