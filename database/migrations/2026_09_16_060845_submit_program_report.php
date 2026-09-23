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
        Schema::create('submit_program_report', function (Blueprint $table) {
            $table->id();

            // Who submitted the report
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Which PHO form was submitted: m1, q1, m2, a1, mo
            $table->string('form', 10);

            // Reporting period
            $table->string('month', 2);   // '01' - '12'
            $table->string('year', 4);    // 'YYYY'

            // Location snapshot at time of submission
            $table->string('region_code')->nullable();
            $table->string('province_code')->nullable();
            $table->string('municipality_code')->nullable();
            $table->json('barangay_codes')->nullable();

            // Submission status: submitted, approved, rejected, etc.
            $table->string('status')->default('submitted');

            $table->timestamps();

            // Prevent the same user from submitting the same form/period twice
            $table->unique(
                ['user_id', 'form', 'month', 'year'],
                'submit_program_report_unique_period'
            );

            $table->index(
                ['region_code', 'province_code', 'municipality_code'],
                'submit_program_report_location_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submit_program_report');
    }
};