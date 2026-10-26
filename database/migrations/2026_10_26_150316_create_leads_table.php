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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('new');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('urgency');
            // Door size, style, symptoms, and hurricane-check answers, depending on the service.
            $table->jsonb('answers')->default('{}');
            $table->text('message')->nullable();
            $table->string('name');
            // Stored lowercase, and the phone as E.164, so earlier requests from the same person match.
            $table->string('email');
            $table->string('phone');
            $table->string('preferred_contact');
            $table->string('street');
            $table->string('town');
            $table->string('zip', 10);
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            // utm_*, gclid, landing page, and referrer from the visitor's first page view.
            $table->jsonb('attribution')->nullable();
            $table->boolean('sms_consent')->default(false);
            $table->boolean('marketing_consent')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('phone');
        });

        DB::statement('create index leads_email_lower_index on leads (lower(email))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
