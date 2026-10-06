<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('guard_name');
            // System roles are seeded and cannot be deleted or renamed.
            $table->boolean('is_system')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.roles'), function (Blueprint $table) {
            $table->dropColumn(['description', 'is_system']);
        });
    }
};
