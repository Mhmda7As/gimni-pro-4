<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0 (Sunday) to 6 (Saturday), null if exception
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->boolean('is_exception')->default(false);
            $table->date('exception_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id', 'day_of_week', 'is_exception']);
            $table->index(['provider_id', 'exception_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_schedules');
    }
};