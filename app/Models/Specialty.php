<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specialty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'is_booking_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_booking_enabled' => 'boolean',
        ];
    }

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }
}