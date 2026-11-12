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
        Schema::table('clients', function (Blueprint $table) {
            // The linked customer's fields as both sides last agreed (App\Services\QuickBooks\CustomerFields),
            // so a later change on either side shows which side made it.
            $table->jsonb('qb_synced_fields')->nullable();
            // clean | pending (a change waiting to go to QuickBooks) | error. Null when not linked.
            $table->string('qb_sync_state')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['qb_synced_fields', 'qb_sync_state']);
        });
    }
};
