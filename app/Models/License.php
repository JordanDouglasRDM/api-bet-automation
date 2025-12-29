<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ramsey\Uuid\Uuid;

class License extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'uuid',
        'status',
        'start_at',
        'expires_at',
        'lifetime',
        'cambistas_ativos_count',
        'last_use',
        'price',
        'indication',
    ];

    protected $casts = [
        'start_at'     => 'datetime',
        'expires_at'   => 'datetime',
        'activated_at' => 'datetime',
        'last_use'     => 'datetime',
    ];

    public const array STATUS_TRANSLATE = [
        'active'   => 'Ativa',
        'inactive' => 'Inativa',
        'revoked'  => 'Revogada',
        'pending'  => 'Pendente',
        'expired'  => 'Expirada',
    ];
    public const array SEVERITY_TAG = [
        'active'   => 'success',
        'inactive' => 'danger',
        'revoked'  => 'help',
        'pending'  => 'warn',
        'expired'  => 'danger',
    ];

    protected static function booted(): void
    {
        static::creating(function (License $license) {
            if (empty($license->uuid)) {
                $license->uuid = Uuid::uuid4()->toString();
            }
        });
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSeverityTag(): ?string
    {
        return self::SEVERITY_TAG[$this->status];
    }

    public function getStatusTranslated(): ?string
    {
        return self::STATUS_TRANSLATE[$this->status];
    }

    public function isValid(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->lifetime) return true;
        return now()->lte($this->expires_at->endOfDay());
    }
}
