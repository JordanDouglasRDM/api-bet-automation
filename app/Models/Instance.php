<?php

declare(strict_types = 1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Instance extends BaseModel
{
    protected $table = 'instancias';

    protected $fillable = [
        'id',
        'nome',
        'auth_id',
        'created_at',
        'updated_at',
    ];

    public function instanceUsers(): HasMany
    {
        return $this->hasMany(InstanceUser::class, 'instancia_id', 'id');
    }
}
