<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('schistosomiasis_registry', function (Blueprint $table) {
            $table->id();
            $table->string('date_of_registration')->nullable();
            $table->string('family_serial_number')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('residency')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->string('age_group')->nullable();
            $table->string('sex')->nullable();
            
            $table->string('history_of_exposure')->nullable();
            $table->string('screened')->nullable();
            $table->string('date_screened')->nullable();
            
            $table->string('with_signs_symptoms')->nullable();
            $table->json('signs_symptoms')->nullable(); // Used for List<String>
            $table->string('signs_symptoms_other_specify')->nullable();
            
            $table->string('clinical_first_treatment_given')->nullable();
            $table->string('clinical_first_treatment_date')->nullable();
            $table->string('clinical_retreatment')->nullable();
            $table->string('clinical_retreatment_date')->nullable();
            $table->string('clinical_cured')->nullable();
            $table->string('clinical_cured_date')->nullable();
            
            $table->string('diagnostic_test')->nullable();
            $table->string('date_of_diagnosis')->nullable();
            $table->string('diagnostic_result')->nullable();
            $table->string('date_confirmed')->nullable();
            
            $table->string('complicated')->nullable();
            $table->string('confirmed_first_treatment_given')->nullable();
            $table->string('confirmed_first_treatment_date')->nullable();
            $table->string('confirmed_retreatment')->nullable();
            $table->string('confirmed_retreatment_date')->nullable();
            $table->string('confirmed_cured')->nullable();
            $table->string('confirmed_cured_date')->nullable();
            
            $table->string('date_referred_to_hospital')->nullable();
            $table->string('mda_given')->nullable();
            $table->string('mda_date_given')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('schistosomiasis_registry');
    }
};
