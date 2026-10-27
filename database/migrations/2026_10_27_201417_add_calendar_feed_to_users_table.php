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
        Schema::table('users', function (Blueprint $table) {
            // SHA-256 of the secret in the visits feed URL. The URL is shown once, when it's made.
            $table->string('calendar_feed_token', 64)->nullable()->unique();
            // When a calendar app last fetched the feed, so the owner can see how far behind it runs.
            $table->timestamp('calendar_feed_read_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['calendar_feed_token', 'calendar_feed_read_at']);
        });
    }
};
