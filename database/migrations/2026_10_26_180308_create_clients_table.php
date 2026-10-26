<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            // Unique, ignoring case, because QuickBooks refuses two customers with the same display name.
            $table->string('display_name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            // Stored lowercase, and the phone as E.164, so a quote request from the same person matches.
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('billing_street')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_state', 2)->nullable();
            $table->string('billing_zip', 10)->nullable();
            $table->boolean('is_property_manager')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
        });

        DB::statement('create unique index clients_display_name_lower_unique on clients (lower(display_name))');
        DB::statement('create index clients_email_lower_index on clients (lower(email))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
