<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The catalogue item ("Robe MegaPointe", "16A cable 10 m").
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('equipment_categories')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('sku', 50)->unique();
            $table->string('manufacturer', 100)->nullable()->index();
            $table->string('model', 100)->nullable();
            $table->string('tracking_mode', 20); // serialized | bulk
            $table->string('unit', 30)->default('unit'); // lookups: unit
            $table->string('asset_prefix', 10)->nullable(); // ML → ML-001 (serialized only)
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('replacement_value_kobo')->nullable();
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'is_active']);
            $table->index('name');
        });

        // One physical, individually tracked unit.
        Schema::create('equipment_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->string('asset_tag', 50)->unique();
            $table->string('serial_number', 100)->nullable();
            $table->string('barcode', 100)->nullable()->unique();
            $table->ulid('qr_token')->unique(); // opaque scan identifier: /app/scan/{qr_token}
            $table->foreignId('status_id')->constrained('asset_statuses')->restrictOnDelete();
            $table->string('condition', 50); // lookups: condition
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('purchase_cost_kobo')->nullable();
            $table->unsignedBigInteger('current_value_kobo')->nullable();
            $table->string('supplier', 150)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->timestamp('last_inspected_at')->nullable();
            $table->date('next_maintenance_due_on')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['equipment_id', 'serial_number']);
            $table->index(['equipment_id', 'status_id']);
            $table->index(['location_id', 'status_id']);
            $table->index('condition');
        });

        // Quantity-tracked stock per location. "available" can be allocated;
        // "quarantine" holds damaged or uninspected units.
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('bucket', 20)->default('available');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['equipment_id', 'location_id', 'bucket']);
        });

        // Immutable movement ledger. event_id gets its foreign key in Phase 4.
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('equipment_assets')->restrictOnDelete();
            $table->integer('quantity')->default(1);
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('asset_statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->nullable()->constrained('asset_statuses')->restrictOnDelete();
            $table->string('from_condition', 50)->nullable();
            $table->string('to_condition', 50)->nullable();
            $table->string('from_bucket', 20)->nullable();
            $table->string('to_bucket', 20)->nullable();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('created_at')->nullable();

            $table->index(['equipment_id', 'occurred_at']);
            $table->index(['asset_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('equipment_assets');
        Schema::dropIfExists('equipment');
    }
};
