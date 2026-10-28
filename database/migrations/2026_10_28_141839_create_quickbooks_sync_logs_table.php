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
        // Every call to Intuit, with the intuit_tid its support asks for. Kept 30 days.
        Schema::create('quickbooks_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quickbooks_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->string('path');
            // Null when QuickBooks couldn't be reached at all.
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('intuit_tid')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_sync_logs');
    }
};
