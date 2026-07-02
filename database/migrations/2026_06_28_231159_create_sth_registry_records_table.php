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
        Schema::create('sth_registry_records', function (Blueprint $table) {
            $table->id();
            $table->string('date_of_registration')->nullable();
            $table->string('family_serial_number')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('residency')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->string('age_classification')->nullable();
            $table->string('sex')->nullable();
            
            $table->string('screened')->nullable();
            $table->string('date_of_screening')->nullable();
            $table->string('screening_result')->nullable();
            $table->string('date_of_result')->nullable();
            
            $table->string('treatment_given')->nullable();
            $table->string('treatment_date_given')->nullable();
            
            $table->string('january_mda_date')->nullable();
            $table->string('january_mda_modality')->nullable();
            $table->string('july_mda_date')->nullable();
            $table->string('july_mda_modality')->nullable();
            
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sth_registry_records');
    }
};
