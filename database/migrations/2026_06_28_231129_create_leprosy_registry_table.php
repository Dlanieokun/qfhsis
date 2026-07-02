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
        Schema::create('leprosy_registry', function (Blueprint $table) {
            $table->id();
            $table->string('date_of_registration')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->string('age_group')->nullable();
            $table->string('sex')->nullable();
            
            $table->string('confirmed_case')->nullable();
            $table->string('date_of_diagnosis')->nullable();
            $table->text('case_history')->nullable();
            $table->string('previous_facility')->nullable();
            $table->string('clinical_classification')->nullable();
            
            $table->string('treatment_start_date')->nullable();
            $table->string('months_treated_prior')->nullable();
            $table->string('reclassified')->nullable();
            $table->string('date_of_reclassification')->nullable();
            $table->string('updated_classification')->nullable();
            
            $table->string('treatment_outcome')->nullable();
            $table->string('completed_fixed_mdt')->nullable();
            $table->string('fixed_mdt_completed_date')->nullable();
            $table->string('beyond_fixed_mdt')->nullable();
            $table->string('beyond_fixed_mdt_completed_date')->nullable();
            
            $table->string('grade2_disability')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('leprosy_registry');
    }
};
