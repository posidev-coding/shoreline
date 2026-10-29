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
        // What went wrong syncing with QuickBooks and needs a person: one open row per problem,
        // counting how often it recurs, until it's retried successfully or marked resolved.
        Schema::create('quickbooks_sync_issues', function (Blueprint $table) {
            $table->id();
            $table->string('realm_id')->nullable();
            $table->string('kind');
            // The record it's about, when it's about one.
            $table->string('entity')->nullable();
            $table->string('qb_id')->nullable();
            $table->text('message');
            $table->string('intuit_tid')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'entity', 'qb_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quickbooks_sync_issues');
    }
};
