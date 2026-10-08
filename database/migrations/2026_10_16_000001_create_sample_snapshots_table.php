<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The real inventory as it was before sample data was loaded; restored when it is cleared (D73).
        Schema::create('sample_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->string('record_key', 64);
            $table->longText('data');

            $table->index(['table_name', 'record_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_snapshots');
    }
};
