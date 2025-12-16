<?php

declare(strict_types = 1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'start_at',
        'expires_at',
        'lifetime',
        'activated_at', //a partir de quando que o usuário pode usar
        'last_use',
    ];

    protected $casts = [
        'start_at'     => 'datetime',
        'expires_at'   => 'datetime',
        'activated_at' => 'datetime',
        'last_use'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->status === 'active'
            && $this->starts_at->lte(now())
            && $this->activated_at !== null
            && $this->expires_at->gte(now());
    }
}
