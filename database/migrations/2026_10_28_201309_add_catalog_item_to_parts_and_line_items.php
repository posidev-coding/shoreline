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
        // The QuickBooks item a part bills as, and the one each line bills as (picked from the
        // part, or chosen for a line with no part, like labor).
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('project_line_items', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_line_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_item_id');
        });

        Schema::table('parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_item_id');
        });
    }
};
