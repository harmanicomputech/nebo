<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What an event needs.
        Schema::create('equipment_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'equipment_id']);
        });

        // What an event holds. One row per serialized asset, or a quantity of
        // a bulk item. Active states (reserved, checked_out) block the window.
        Schema::create('equipment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('equipment_assets')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete(); // bulk: where it comes from
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('hold_starts_at');
            $table->timestamp('hold_ends_at');
            $table->string('state', 20)->default('reserved'); // reserved | checked_out | returned | released
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('return_outcome', 30)->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'state', 'hold_starts_at', 'hold_ends_at'], 'alloc_equipment_window');
            $table->index(['asset_id', 'state', 'hold_starts_at', 'hold_ends_at'], 'alloc_asset_window');
            $table->index(['event_id', 'state']);
        });

        Schema::create('load_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 20)->default('pending'); // pending | picked | loaded | checked | dispatched
            $table->string('notes', 2000)->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('load_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_id')->unique()->constrained('equipment_allocations')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | picked | loaded | checked
            $table->string('case_label', 60)->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('notes', 2000)->nullable();
            $table->unsignedInteger('returned_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('damaged_count')->default(0);
            $table->timestamps();
        });

        Schema::create('return_check_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('equipment_allocations')->restrictOnDelete();
            $table->string('outcome', 30); // returned | missing | damaged | needs_inspection | needs_maintenance
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_check_items');
        Schema::dropIfExists('return_checks');
        Schema::dropIfExists('load_list_items');
        Schema::dropIfExists('load_lists');
        Schema::dropIfExists('equipment_allocations');
        Schema::dropIfExists('equipment_requirements');
    }
};
