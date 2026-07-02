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
        Schema::create('oral_health_care', function (Blueprint $table) {
            $table->id();
            // Added profile_id to map with Android Room Entity tracking
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->foreign('profile_id')->references('id')->on('household_profiles')->onDelete('cascade');
            
            $table->string('date_of_visit')->nullable();
            $table->string('family_serial')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('age_months')->nullable();
            $table->string('sex')->nullable();
            
            $table->boolean('rpoc0_oral_screening')->default(false);
            $table->boolean('rpoc0_risk_assessment')->default(false);
            $table->boolean('rpoc0_oral_hygiene')->default(false);
            $table->boolean('rpoc0_counseling')->default(false);
            $table->boolean('rpoc0_fluoride_varnish')->default(false);
            $table->integer('complete_rpoc0')->nullable();
            
            $table->string('age_years')->nullable();
            $table->string('age_group1st')->nullable();
            $table->string('age_group2nd')->nullable();
            
            $table->string('oral_screening1st')->nullable();
            $table->string('oral_screening2nd')->nullable();
            $table->string('risk_assessment1st')->nullable();
            $table->string('risk_assessment2nd')->nullable();
            $table->string('oral_prophylaxis1st')->nullable();
            $table->string('oral_prophylaxis2nd')->nullable();
            $table->string('fluoride_varnish1st')->nullable();
            $table->string('fluoride_varnish2nd')->nullable();
            $table->string('counseling1st')->nullable();
            $table->string('counseling2nd')->nullable();
            
            $table->integer('complete_rpoc1st')->nullable();
            $table->integer('complete_rpoc2nd')->nullable();
            
            $table->string('service_location1st')->nullable();
            $table->string('service_location2nd')->nullable();
            
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('oral_health_care');
    }
};