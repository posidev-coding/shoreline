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
        // QuickBooks' products and services, pulled from QuickBooks and never pushed (v1). Every
        // estimate and invoice line bills as one of them.
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id');
            $table->string('qb_id');
            $table->string('qb_sync_token')->nullable();
            $table->timestamp('qb_last_updated_at')->nullable();
            $table->jsonb('qb_payload');
            $table->string('name');
            // "Parent:Child" for an item under a category.
            $table->string('fully_qualified_name');
            $table->string('type');
            $table->string('parent_qb_id')->nullable();
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->boolean('is_taxable')->default(false);
            $table->string('income_account_qb_id')->nullable();
            $table->string('income_account_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['realm_id', 'qb_id']);
            $table->index(['is_active', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
