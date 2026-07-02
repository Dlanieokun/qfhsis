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
        Schema::create('fhsis_reports', function (Blueprint $table) {
            $table->id();
            // Connects each health report directly to the health worker who submitted it
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reporting_year', 4);
            $table->string('reporting_quarter'); // Q1, Q2, Q3, Q4
            
            // Maternal Health Care Indicators
            $table->integer('total_pregnant_tracked')->default(0);
            $table->integer('completed_4_anc_visits')->default(0);
            
            // Child Immunization Indicators
            $table->integer('fully_immunized_children')->default(0);
            $table->integer('infants_exclusive_breastfed')->default(0);

            $table->string('status')->default('submitted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fhsis_reports');
    }
};