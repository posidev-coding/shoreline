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
        // A ledger: rows are only ever added, and a part's on_hand is the sum of its rows.
        Schema::create('part_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            // Signed: received and returned add, used takes away, adjusted goes either way.
            $table->decimal('quantity', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_line_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['part_id', 'created_at']);
        });

        // A line item's parts are received into stock once and used once, however often the
        // order is re-marked or the project is reopened and completed again.
        DB::statement('create unique index part_stock_movements_line_item_type_unique on part_stock_movements (project_line_item_id, type) where project_line_item_id is not null');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('part_stock_movements');
    }
};
