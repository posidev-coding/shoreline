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
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('category')->index();
            // What it's called on a line item, like "Torsion spring 0.243 x 2 x 32, left wind".
            $table->string('name');
            $table->string('manufacturer')->nullable()->index();
            $table->string('model')->nullable();
            $table->string('sku')->nullable();
            // Category-specific details: a spring's wire size and wind, an opener's drive and horsepower.
            $table->jsonb('specs')->default('{}');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit')->default('each');
            // Per unit, with the extra precision line-item rates have.
            $table->decimal('cost', 12, 4)->nullable();
            $table->decimal('price', 12, 4)->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->boolean('is_active')->default(true);
            // Stock is kept here, not in QuickBooks: QuickBooks inventory needs QBO Plus.
            $table->boolean('track_stock')->default(false);
            // Changed only through part_stock_movements, which it always equals the sum of.
            $table->decimal('on_hand', 10, 2)->default(0);
            $table->decimal('reorder_point', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
