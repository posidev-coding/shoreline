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
        Schema::create('project_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->index()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1);
            // More precision than cents, as QuickBooks allows, so a rate like $0.4375 a foot survives.
            $table->decimal('unit_price', 12, 4)->default(0);
            // QuickBooks' TAX/NON code. QuickBooks computes the tax itself.
            $table->boolean('is_taxable')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_line_items');
    }
};
