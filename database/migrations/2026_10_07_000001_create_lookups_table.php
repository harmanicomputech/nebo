<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable option lists (condition grades, location types, units, …).
        // Records store the key; labels can be renamed freely.
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50);
            $table->string('key', 50);
            $table->string('label', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index(['group', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
