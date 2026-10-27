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
        Schema::create('installed_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->index()->constrained()->cascadeOnDelete();
            // Springs, opener, and hardware sit under the door they belong to.
            $table->foreignId('parent_id')->nullable()->index()->constrained('installed_equipment')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial')->nullable();
            $table->jsonb('specs')->default('{}');
            // Doors only. Design pressure is in pounds per square foot, from the door's label.
            $table->string('size')->nullable();
            $table->string('material')->nullable();
            $table->decimal('design_pressure_positive', 5, 1)->nullable();
            $table->decimal('design_pressure_negative', 5, 1)->nullable();
            $table->boolean('is_impact_rated')->default(false);
            $table->date('installed_on')->nullable();
            $table->foreignId('installed_by_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->date('warranty_expires_on')->nullable();
            $table->date('last_serviced_on')->nullable();
            $table->date('maintenance_due_on')->nullable()->index();
            $table->string('status')->default('active');
            $table->foreignId('replaced_by_id')->nullable()->constrained('installed_equipment')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installed_equipment');
    }
};
