<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetCode extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'is_used'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isValid()
    {
        return !$this->is_used && !$this->isExpired();
    }
}
