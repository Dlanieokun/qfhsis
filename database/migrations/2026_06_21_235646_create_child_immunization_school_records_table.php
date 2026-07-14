<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_immunization_school_records', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');

            // Demographics Tracking
            $table->string('registrationDate')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->string('childName')->nullable();
            $table->string('dateOfBirth')->nullable();
            $table->string('ageYears')->nullable();
            $table->string('sex')->nullable();
            $table->string('address')->nullable();
            $table->string('gradeLevel')->nullable();

            // School-Based Immunization (SBI) Vaccines
            $table->string('tdDate')->nullable();
            $table->string('mrDate')->nullable();
            $table->string('hpv1SbiDate')->nullable();

            // Community-Based Immunization (CBI) Vaccines
            $table->string('hpv1CbiDate')->nullable();
            $table->string('hpv2CbiDate')->nullable();

            // HPV Fully Immunized Female (FIF) Metrics
            $table->tinyInteger('hpvCompleted')->default(0);
            $table->string('hpvCompletedDate')->nullable();

            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_immunization_school_records');
    }
};