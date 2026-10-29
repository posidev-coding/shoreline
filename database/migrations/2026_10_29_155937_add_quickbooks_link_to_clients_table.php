<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The QuickBooks customer a client is, once matched. The two-way sync's own columns
        // (last_synced_hash, sync_state, qb_mode) arrive with it in M6.
        Schema::table('clients', function (Blueprint $table) {
            $table->string('realm_id')->nullable();
            $table->string('qb_id')->nullable();
            $table->string('qb_sync_token')->nullable();
            $table->timestamp('qb_last_updated_at')->nullable();
            $table->timestamp('qb_linked_at')->nullable();

            $table->unique(['realm_id', 'qb_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['realm_id', 'qb_id']);
            $table->dropColumn(['realm_id', 'qb_id', 'qb_sync_token', 'qb_last_updated_at', 'qb_linked_at']);
        });
    }
};
