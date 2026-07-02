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
        Schema::disableForeignKeyConstraints();

        Schema::create('prenatal_lab_screening_records', function (Blueprint $table) {
            $table->id();
            
            // Relational Foreign Key reference linking back to the parent MaternalCareRecord row
            $table->foreignId('maternalRecordId')
                  ->constrained('maternal_care_records')
                  ->onDelete('cascade');

            // Complete Laboratory Assessment Checkpoints Metrics Tracker Columns
            // Complete Blood Count (CBC Tracking Metrics parameters)
            $table->string('cbcDate')->nullable();
            $table->string('cbcResult')->nullable();
            $table->text('cbcRemarks')->nullable();

            // Gestational Diabetes Mellitus (GDM Screening parameters)
            $table->string('gdmDate')->nullable();
            $table->string('gdmResult')->nullable();
            $table->text('gdmRemarks')->nullable();

            // Hepatitis B Screening Diagnostic Metrics
            $table->string('hepBDate')->nullable();
            $table->string('hepBResult')->nullable();
            $table->text('hepBRemarks')->nullable();

            // Human Immunodeficiency Virus (HIV Screening parameters)
            $table->string('hivDate')->nullable();
            $table->string('hivResult')->nullable();
            $table->text('hivRemarks')->nullable();

            // Syphilis VDRL/RPR Screening Diagnostics parameters
            $table->string('syphilisDate')->nullable();
            $table->string('syphilisResult')->nullable();
            $table->text('syphilisRemarks')->nullable();
            $table->string('syphilisConfirmatoryDate')->nullable();
            $table->string('syphilisConfirmatoryResult')->nullable();
            $table->string('syphilisTreatment')->nullable();

            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('prenatal_lab_screening_records'); 
        Schema::enableForeignKeyConstraints();
    }
};