<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('child_care_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->string('registrationDate')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->string('childName')->nullable();
            $table->string('sex')->nullable();
            $table->string('birthDate')->nullable();
            $table->string('birthWeight')->nullable(); 
            $table->string('birthStatus')->nullable();  

            // NIP Schedules
            $table->string('bcgDate')->nullable();
            $table->string('hepaBDate')->nullable();
            $table->string('penta1Date')->nullable();
            $table->string('penta2Date')->nullable();
            $table->string('penta3Date')->nullable();
            $table->string('opv1Date')->nullable();
            $table->string('opv2Date')->nullable();
            $table->string('opv3Date')->nullable();
            $table->string('ipv1Date')->nullable();
            $table->string('ipv2Date')->nullable();
            $table->string('pcv1Date')->nullable();
            $table->string('pcv2Date')->nullable();
            $table->string('pcv3Date')->nullable();
            $table->string('mcv1Date')->nullable();
            $table->string('mcv2Date')->nullable();
            $table->string('ficDate')->nullable(); 

            // Nutrition & Interventions
            $table->string('exclusiveBreastfeeding')->nullable(); 
            $table->string('vitA1Date')->nullable();
            $table->string('vitA2Date')->nullable();
            $table->string('mnpDate')->nullable(); 
            $table->string('dewormingDate')->nullable();
            
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('child_care_records');
        Schema::enableForeignKeyConstraints();
    }
};