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
        // QuickBooks invoices as last pulled, never edited here. Deleted in QuickBooks means
        // soft-deleted here; voided ones stay, at zero.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id');
            $table->string('qb_id');
            $table->string('qb_sync_token')->nullable();
            $table->timestamp('qb_last_updated_at')->nullable();
            $table->jsonb('qb_payload');
            $table->string('customer_qb_id');
            $table->string('client_qb_id');
            $table->string('customer_name')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            // The estimate it was made from (LinkedTxn), when there is one.
            $table->string('estimate_qb_id')->nullable();
            $table->string('doc_number')->nullable();
            $table->date('txn_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('total_amt', 12, 2);
            $table->decimal('balance', 12, 2);
            $table->boolean('is_voided')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['realm_id', 'qb_id']);
            $table->index('client_qb_id');
            $table->index('estimate_qb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
