<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialty_id')->constrained('specialties')->restrictOnDelete();
            $table->string('name');
            $table->string('title')->nullable(); // e.g., Dr., Prof. Dr., Lab Unit
            $table->enum('type', ['doctor', 'radiology', 'lab'])->default('doctor');
            $table->boolean('is_recurring')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['specialty_id', 'is_active']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};