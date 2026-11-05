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
        Schema::table('quickbooks_connections', function (Blueprint $table) {
            // The hourly sweep's cursor: QuickBooks' clock when it last asked for changes.
            $table->timestamp('changes_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quickbooks_connections', function (Blueprint $table) {
            $table->dropColumn('changes_synced_at');
        });
    }
};
