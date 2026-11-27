<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->restrictOnDelete();
            $table->foreignId('time_slot_id')->nullable()->constrained('time_slots')->nullOnDelete();
            $table->date('appointment_date');
            $table->time('start_time');
            $table->enum('status', ['scheduled', 'attended', 'cancelled', 'no_show'])->default('scheduled');
            $table->decimal('payment_amount', 10, 2)->default(0.00);
            $table->boolean('is_archived')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['appointment_date', 'status', 'is_archived'], 'idx_appointments_daily');
            $table->index(['patient_id', 'status']);
            $table->index(['provider_id', 'appointment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};