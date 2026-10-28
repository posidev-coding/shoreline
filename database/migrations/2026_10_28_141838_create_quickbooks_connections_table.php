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
        // The app's link to one QuickBooks company. There's only ever one row: reconnecting the
        // same company updates it, and another company is refused until this one is forgotten.
        Schema::create('quickbooks_connections', function (Blueprint $table) {
            $table->id();
            $table->string('environment');
            $table->string('realm_id');
            $table->string('company_name')->nullable();
            // Encrypted with the app key, and cleared on disconnect.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            // The rolling 100-day expiry, and the five-year one Intuit reports separately.
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('refresh_hard_expires_at')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            // The company's Preferences, as QuickBooks last reported them.
            $table->jsonb('preferences')->nullable();
            $table->timestamp('preferences_checked_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->string('disconnect_reason')->nullable();
            $table->timestamps();

            $table->unique(['environment', 'realm_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_connections');
    }
};
