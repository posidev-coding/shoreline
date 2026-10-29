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
        // QuickBooks estimates as last pulled, never edited here. Deleted in QuickBooks means
        // soft-deleted here.
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id');
            $table->string('qb_id');
            $table->string('qb_sync_token')->nullable();
            $table->timestamp('qb_last_updated_at')->nullable();
            $table->jsonb('qb_payload');
            // CustomerRef, and the top-level customer it sits under, which is the one a client links to.
            $table->string('customer_qb_id');
            $table->string('client_qb_id');
            $table->string('customer_name')->nullable();
            // Set when the app sends an estimate for a job (M6).
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('doc_number')->nullable();
            $table->date('txn_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('status');
            $table->string('accepted_by')->nullable();
            $table->date('accepted_date')->nullable();
            $table->decimal('total_amt', 12, 2);
            $table->decimal('total_tax', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['realm_id', 'qb_id']);
            $table->index('client_qb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
