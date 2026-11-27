<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'phone_number',
        'last_registered_name',
        'kvkk_accepted',
        'no_show_count',
        'is_banned',
        'banned_until',
    ];

    protected function casts(): array
    {
        return [
            'kvkk_accepted' => 'boolean',
            'no_show_count' => 'integer',
            'is_banned' => 'boolean',
            'banned_until' => 'datetime',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function isCurrentlyBanned(): bool
{
    if (!$this->is_banned) {
        return false;
    }

    if ($this->banned_until === null) {
        return true;
    }

    return now()->lt($this->banned_until);
}
}