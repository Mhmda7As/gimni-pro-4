<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    protected $primaryKey = 'phone_number';
    public $incrementing = false;
    protected $keyType = 'string';

    public const CREATED_AT = null; // Only updated_at exists in schema

    protected $fillable = [
        'phone_number',
        'current_step',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'updated_at' => 'datetime',
        ];
    }
}