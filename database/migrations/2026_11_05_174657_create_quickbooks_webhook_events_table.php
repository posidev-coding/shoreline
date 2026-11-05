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
        // Each webhook event Intuit sent for the connected company, as it came. Intuit retries a
        // delivery until it's acknowledged, so an event already stored is not processed again.
        // Kept 30 days, for troubleshooting.
        Schema::create('quickbooks_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('realm_id');
            // namespace.entity.event.version, e.g. qbo.customer.merged.v1
            $table->string('type');
            $table->string('qb_id')->nullable();
            $table->jsonb('payload');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_webhook_events');
    }
};
