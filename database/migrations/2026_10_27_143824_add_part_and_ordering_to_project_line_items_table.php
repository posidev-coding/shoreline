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
        Schema::table('project_line_items', function (Blueprint $table) {
            $table->foreignId('part_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            // Null when nothing has to be ordered for this line; otherwise to_order, ordered, or received.
            $table->string('order_status')->nullable()->index()->after('is_taxable');
            $table->foreignId('supplier_id')->nullable()->after('order_status')->constrained()->nullOnDelete();
            $table->date('expected_on')->nullable()->after('supplier_id');
            $table->timestamp('ordered_at')->nullable()->after('expected_on');
            $table->timestamp('received_at')->nullable()->after('ordered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('part_id');
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn(['order_status', 'expected_on', 'ordered_at', 'received_at']);
        });
    }
};
