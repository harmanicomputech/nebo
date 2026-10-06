<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 index review: columns that reports, dashboards and the
 * notification bell filter or sort on and that had no index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->index('sent_at');
            $table->index(['status', 'responded_at']);
        });
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['status', 'completed_at']);
        });
        Schema::table('logistics_trips', function (Blueprint $table) {
            $table->index('departs_at');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex('notifications_unread_index'));
        Schema::table('logistics_trips', fn (Blueprint $table) => $table->dropIndex(['departs_at']));
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'completed_at']);
        });
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['sent_at']);
            $table->dropIndex(['status', 'responded_at']);
        });
    }
};
