<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every row the sample-data loader created, in creation order, so it can be removed later (D71).
        Schema::create('sample_records', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->string('key_name', 64);
            $table->string('record_key', 64);

            $table->index(['table_name', 'record_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_records');
    }
};
