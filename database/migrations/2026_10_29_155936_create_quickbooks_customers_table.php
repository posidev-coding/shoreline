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
        // QuickBooks' customers as last imported, for matching to clients. A match is the client's
        // qb_id; a row here is never edited by hand.
        Schema::create('quickbooks_customers', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id');
            $table->string('qb_id');
            $table->string('qb_sync_token')->nullable();
            $table->timestamp('qb_last_updated_at')->nullable();
            $table->jsonb('qb_payload');
            $table->string('display_name');
            $table->string('company_name')->nullable();
            $table->string('given_name')->nullable();
            $table->string('family_name')->nullable();
            // Stored lowercase and as E.164, like clients', so they compare directly.
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('billing_city')->nullable();
            $table->boolean('is_active')->default(true);
            // A sub-customer (Job) or a QuickBooks Online project, under parent_qb_id.
            $table->string('parent_qb_id')->nullable();
            $table->boolean('is_project')->default(false);
            // Left out of matching by the owner.
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['realm_id', 'qb_id']);
            $table->index('phone');
        });

        DB::statement('create index quickbooks_customers_email_lower_index on quickbooks_customers (lower(email))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_customers');
    }
};
