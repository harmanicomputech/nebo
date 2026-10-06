<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recurring work per unit (interval in days). The asset's
        // next_maintenance_due_on mirrors the earliest active schedule.
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('equipment_assets')->cascadeOnDelete();
            $table->string('type', 50); // lookup: maintenance_type
            $table->unsignedSmallInteger('interval_days');
            $table->date('next_due_on');
            $table->date('last_done_on')->nullable();
            $table->date('last_reminded_on')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'type']);
            $table->index(['is_active', 'next_due_on']);
        });

        // A maintenance job: a reported fault, a scheduled service or an
        // inspection follow-up. A scheduled window blocks availability (D50).
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('asset_id')->constrained('equipment_assets')->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('maintenance_schedules')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete(); // reported at check-in
            $table->string('type', 50); // lookup: maintenance_type
            $table->string('priority', 10)->default('normal');
            $table->string('status', 20)->default('reported');
            $table->string('source', 20)->default('manual'); // manual | inspection | return | schedule
            $table->string('issue', 200);
            $table->text('description')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('scheduled_starts_at')->nullable();
            $table->timestamp('scheduled_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('cost_kobo')->nullable();
            $table->text('parts_used')->nullable();
            $table->text('work_done')->nullable();
            $table->string('outcome_condition', 50)->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['status', 'scheduled_starts_at', 'scheduled_ends_at']);
        });

        // Condition history: every inspection and every condition change made
        // by returns or maintenance. Photos are documents on the report.
        Schema::create('condition_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('equipment_assets')->cascadeOnDelete();
            $table->string('from_condition', 50)->nullable();
            $table->string('to_condition', 50);
            $table->string('source', 20); // inspection | return | maintenance
            $table->text('note')->nullable();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['asset_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condition_reports');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('maintenance_schedules');
    }
};
