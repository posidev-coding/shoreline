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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            // The quote request it came from. A request converts once.
            $table->foreignId('lead_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('stage')->default('estimating')->index();
            $table->timestamp('stage_changed_at')->nullable();
            $table->text('lost_reason')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
