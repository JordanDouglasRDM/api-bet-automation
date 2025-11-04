<?php

declare(strict_types = 1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstanceUser extends BaseModel
{
    protected $table = 'instancia_usuarios';

    protected $fillable = [
        'id',
        'auth_id',
        'instancia_id',
        'usuario_id',
        'login',
        'saldo',
        'created_at',
        'updated_at',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class, 'instancia_id', 'id');
    }
}
