<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_booking_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_booking_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialties');
    }
};