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
        // QuickBooks payments as last pulled, and what each was applied to.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id');
            $table->string('qb_id');
            $table->string('qb_sync_token')->nullable();
            $table->timestamp('qb_last_updated_at')->nullable();
            $table->jsonb('qb_payload');
            $table->string('customer_qb_id');
            $table->string('client_qb_id');
            $table->string('customer_name')->nullable();
            $table->date('txn_date')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('total_amt', 12, 2);
            $table->decimal('unapplied_amt', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['realm_id', 'qb_id']);
            $table->index('client_qb_id');
        });

        // A payment's lines: how much went to which invoice (or credit memo, or other transaction).
        // Replaced whole each time the payment is pulled.
        Schema::create('payment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('txn_type');
            $table->string('txn_qb_id');
            $table->decimal('amount', 12, 2);

            $table->index(['txn_type', 'txn_qb_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_applications');
        Schema::dropIfExists('payments');
    }
};
