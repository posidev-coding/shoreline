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
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->index()->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('street');
            $table->string('city');
            $table->string('state', 2)->default('NC');
            $table->string('zip', 10);
            $table->string('county')->nullable();
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('primary');
            // Lockbox and gate codes, encrypted with the app key.
            $table->text('access_info')->nullable();
            $table->string('site_contact_name')->nullable();
            $table->string('site_contact_phone')->nullable();
            $table->string('turnover_notes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
