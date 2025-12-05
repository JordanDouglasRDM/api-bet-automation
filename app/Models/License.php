<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class License extends BaseModel
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status',
        'start_at', //inicia no primeiro login
        'expires_at',
        'activated_at', //a partir de quando que o usuário pode usar
    ];
    protected $casts = [
        'start_at'    => 'datetime',
        'expires_at'   => 'datetime',
        'activated_at' => 'datetime',
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
