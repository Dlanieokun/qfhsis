<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_sick_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');

            // Section 1 — Basic Information
            $table->string('dateRegistration')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->string('childName')->nullable();
            $table->string('dateOfBirth')->nullable();
            $table->string('ageMonths')->nullable();
            $table->string('sex')->nullable();
            $table->string('motherName')->nullable();
            $table->string('address')->nullable();

            // Section 2 — Vitamin A Supplementation
            $table->string('vitaminADateGiven')->nullable();
            $table->boolean('vitaminA100IU')->default(false);
            $table->boolean('vitaminA200IU')->default(false);

            // Section 3 — Diagnosis & Management
            $table->boolean('diagnosisMeasles')->default(false);
            $table->boolean('diagnosisPersistentDiarrhea')->default(false);
            $table->string('diarrheaDateGiven')->nullable();
            $table->boolean('orsOnly')->default(false);
            $table->boolean('orsAndZinc')->default(false);

            $table->string('pneumoniaDateGiven')->nullable();
            $table->boolean('amoxicillinDrops')->default(false);
            $table->boolean('amoxicillinClavulanate')->default(false);
            $table->boolean('cefuroxime')->default(false);
            $table->boolean('pneumoniaOthers')->default(false);
            $table->string('pneumoniaOthersSpec')->nullable();

            // Section 4 — Remarks
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_sick_records');
    }
};