<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Production services (Stage & Staging, LED Screens, …). Data, not code.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 500)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(true); // offered on the request form
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150); // contact person
            $table->string('company', 150)->nullable();
            $table->string('email')->nullable()->index(); // lower-case
            $table->string('phone', 20)->nullable()->index(); // E.164 (+234…)
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('needs_review')->default(false); // matched with differing details
            $table->string('source', 30)->default('internal'); // public_form | internal
            $table->timestamps();
            $table->softDeletes();

            $table->index('company');
        });

        Schema::create('event_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->ulid('public_token')->unique(); // customer tracking link
            $table->uuid('submission_key')->nullable()->unique(); // makes a resubmitted form idempotent
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('event_name', 200);
            $table->string('event_type', 50); // lookups: event_type
            $table->string('event_type_other', 150)->nullable();
            $table->date('event_date');
            $table->string('venue', 500);
            $table->json('venue_meta')->nullable(); // structured location later (city, state, lat/lng)
            $table->string('contact_person', 150);
            $table->string('company', 150)->nullable();
            $table->string('email');
            $table->string('phone', 20);
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('setup_at')->nullable();
            $table->boolean('has_existing_design')->default(false);
            $table->string('budget_range', 50)->nullable(); // lookups: budget_range
            $table->text('requirements');
            $table->text('additional_info')->nullable();
            $table->string('services_other', 255)->nullable();
            $table->string('status', 40)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedBigInteger('converted_event_id')->nullable()->index(); // FK added with events
            $table->timestamps();

            $table->index(['status', 'event_date']);
            $table->index('created_at');
        });

        Schema::create('event_request_service', function (Blueprint $table) {
            $table->foreignId('event_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->primary(['event_request_id', 'service_id']);
        });

        // Workflow history for requests, events, quotations, load lists (D11).
        Schema::create('status_changes', function (Blueprint $table) {
            $table->id();
            $table->morphs('statusable');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('created_at');
        });

        // Internal notes on any record (requests, events, customers, …).
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->text('body');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        // Files on any record, on the private disk (D15).
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('category', 50)->nullable(); // lookups: document_category
            $table->string('original_name');
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_by_name')->nullable();
            $table->string('source', 20)->default('internal'); // public_form | internal
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('status_changes');
        Schema::dropIfExists('event_request_service');
        Schema::dropIfExists('event_requests');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('services');
    }
};
