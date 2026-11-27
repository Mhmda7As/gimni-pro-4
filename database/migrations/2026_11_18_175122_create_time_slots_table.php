<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['available', 'booked', 'cooldown'])->default('available');
            $table->timestamp('available_at')->nullable(); // Populated when cancelled (now + 10 mins)
            $table->timestamps();
            $table->softDeletes();

            // Prevent duplicate slots for the same provider at the exact same date & time
            $table->unique(['provider_id', 'date', 'start_time'], 'unique_provider_slot');
            $table->index(['provider_id', 'date', 'status', 'available_at'], 'idx_slot_availability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};