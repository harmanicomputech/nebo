<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CRM profile fields. `notes` shadowed the polymorphic notes relation (D58).
        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('notes', 'remarks');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->after('company'); // lookup: customer_type
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
        });

        // Internal day rate used to price equipment on quotations.
        Schema::table('equipment', function (Blueprint $table) {
            $table->unsignedBigInteger('day_rate_kobo')->nullable()->after('replacement_value_kobo');
        });

        // Ready-made bundles that prefill a quotation.
        Schema::create('production_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->string('event_type', 50)->nullable(); // lookup: event_type
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('production_packages')->cascadeOnDelete();
            $table->string('section', 20); // services | equipment | labour | transport | other
            $table->string('description', 255);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedSmallInteger('days')->default(1);
            $table->unsignedBigInteger('unit_price_kobo')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->ulid('public_token')->unique(); // customer's private link (D62)
            $table->unsignedSmallInteger('revision')->default(1);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('status', 20)->default('draft'); // draft | sent | accepted | declined | expired | cancelled
            $table->date('issued_on')->nullable();
            $table->date('valid_until');
            $table->unsignedBigInteger('subtotal_kobo')->default(0);
            $table->unsignedBigInteger('discount_kobo')->default(0);
            $table->unsignedSmallInteger('tax_rate_bp')->default(0); // basis points: 750 = 7.5%
            $table->unsignedBigInteger('tax_kobo')->default(0);
            $table->unsignedBigInteger('total_kobo')->default(0);
            $table->text('intro')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->string('responded_by_name', 150)->nullable();
            $table->string('response_note', 1000)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'valid_until']);
            $table->index('customer_id');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);
            $table->string('description', 255);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedSmallInteger('days')->default(1);
            $table->unsignedBigInteger('unit_price_kobo')->default(0);
            $table->unsignedBigInteger('line_total_kobo')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('production_packages');
        Schema::table('equipment', fn (Blueprint $table) => $table->dropColumn('day_rate_kobo'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['type', 'city', 'state']));
        Schema::table('customers', fn (Blueprint $table) => $table->renameColumn('remarks', 'notes'));
    }
};
