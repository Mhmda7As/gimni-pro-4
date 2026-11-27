<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 25)->unique(); // E.164 format e.g. 905551234567
            $table->string('last_registered_name')->nullable();
            $table->boolean('kvkk_accepted')->default(false);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_banned', 'banned_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};