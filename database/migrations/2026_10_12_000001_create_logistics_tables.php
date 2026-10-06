<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('registration', 30)->unique();
            $table->string('type', 50); // lookup: vehicle_type
            $table->string('capacity', 120)->nullable(); // e.g. "10 t · 40 m³"
            $table->unsignedInteger('payload_kg')->nullable();
            $table->string('status', 20)->default('active'); // active | out_of_service | retired
            $table->foreignId('default_driver_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('base_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->date('insurance_expires_on')->nullable();
            $table->date('roadworthiness_expires_on')->nullable();
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // A movement of equipment and crew: to a venue, back, or between sites.
        // The scheduled window is what vehicle and driver clashes are checked on (D55).
        Schema::create('logistics_trips', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('event_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction', 20); // outbound | return | transfer
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('origin', 255);
            $table->string('destination', 255);
            $table->timestamp('departs_at');
            $table->timestamp('arrives_at');
            $table->string('status', 20)->default('planned'); // planned | loading | in_transit | arrived | cancelled
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->string('received_by', 120)->nullable(); // who signed for it at the destination
            $table->text('instructions')->nullable(); // for the driver
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'status', 'departs_at', 'arrives_at']);
            $table->index(['status', 'departs_at']);
        });

        Schema::create('logistics_trip_crew', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('logistics_trips')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['trip_id', 'staff_id']);
        });

        // The trip's manifest: which of the event's allocations travel on it.
        Schema::create('logistics_trip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('logistics_trips')->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('equipment_allocations')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['trip_id', 'allocation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_trip_items');
        Schema::dropIfExists('logistics_trip_crew');
        Schema::dropIfExists('logistics_trips');
        Schema::dropIfExists('vehicles');
    }
};
