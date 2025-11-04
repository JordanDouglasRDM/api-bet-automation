<?php

declare(strict_types = 1);

namespace App\Models;

use App\Services\DeviceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Override;

class RefreshToken extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'token',
        'ip',
        'user_agent',
        'revoked_at',
        'device_id',
        'device_name',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token',
        'device_id',
    ];

    #[Override]
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->device_name)) {
                $model->device_name = DeviceService::getDeviceName($model->user_agent);
            }
        });
    }

    public function isValid(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }

    public function anotherDevice(Request $request): bool
    {
        return $this->user_agent !== $request->userAgent();
    }

    /** @return BelongsTo<User, RefreshToken> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
