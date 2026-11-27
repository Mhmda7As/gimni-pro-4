<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeSlot extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'provider_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'available_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'available_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    /**
     * Scope to fetch slots that are either available OR whose 10-minute cooldown has expired.
     */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', 'available')
              ->orWhere(function (Builder $sub) {
                  $sub->where('status', 'cooldown')
                      ->where('available_at', '<=', now());
              });
        });
    }
}