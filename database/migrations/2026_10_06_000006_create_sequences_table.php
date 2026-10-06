<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counters behind reference numbers (NEBO-REQ-2026-00001), row-locked per key and period.
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('period', 20)->default('');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
