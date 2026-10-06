<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People who work events. A login account is optional (D5).
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('role', 50); // lookups: staff_role
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'role']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('event_request_id')->nullable()->unique()->constrained('event_requests')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('name', 200);
            $table->string('event_type', 50);
            $table->string('venue', 500);
            $table->json('venue_meta')->nullable();
            // Hold window for equipment and crew = setup_starts_at → breakdown_ends_at (D8).
            $table->timestamp('setup_starts_at');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('breakdown_ends_at');
            $table->string('status', 30)->default('planning')->index();
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('production_manager_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->unsignedBigInteger('budget_kobo')->nullable();
            $table->text('production_requirements')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['setup_starts_at', 'breakdown_ends_at']);
            $table->index('starts_at');
        });

        Schema::create('event_service', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->primary(['event_id', 'service_id']);
        });

        Schema::create('event_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->string('role', 50); // role on this event (lookups: staff_role)
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'staff_id']);
            $table->index('staff_id');
        });

        Schema::table('event_requests', function (Blueprint $table) {
            $table->foreign('converted_event_id')->references('id')->on('events')->nullOnDelete();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', fn (Blueprint $table) => $table->dropForeign(['event_id']));
        Schema::table('event_requests', fn (Blueprint $table) => $table->dropForeign(['converted_event_id']));
        Schema::dropIfExists('event_staff');
        Schema::dropIfExists('event_service');
        Schema::dropIfExists('events');
        Schema::dropIfExists('staff');
    }
};
