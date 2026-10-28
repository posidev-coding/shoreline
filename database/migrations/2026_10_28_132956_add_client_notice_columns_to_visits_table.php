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
        // When the client was last emailed about the visit, and the window that email gave, so a
        // move is told and a save that changes nothing isn't.
        Schema::table('visits', function (Blueprint $table) {
            $table->timestamp('client_notified_at')->nullable();
            $table->timestamp('notified_starts_at')->nullable();
            $table->timestamp('notified_ends_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['client_notified_at', 'notified_starts_at', 'notified_ends_at']);
        });
    }
};
