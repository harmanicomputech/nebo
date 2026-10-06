<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('equipment_categories')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('code', 30)->unique();
            $table->string('type', 50); // lookups: location_type
            $table->string('address', 500)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'type']);
        });

        // Statuses are data; behaviour comes from `group` (a fixed set the code
        // understands) and the flags, never from labels.
        Schema::create('asset_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 80);
            $table->string('description', 255)->nullable();
            $table->string('group', 30); // App\Enums\AssetStatusGroup
            $table->string('tone', 20)->default('neutral');
            $table->boolean('is_allocatable')->default(false);
            $table->boolean('is_manual')->default(true); // false: only the allocation engine sets it
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_statuses');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('equipment_categories');
    }
};
