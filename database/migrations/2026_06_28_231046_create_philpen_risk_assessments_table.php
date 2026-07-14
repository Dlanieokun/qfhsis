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
        Schema::create('philpen_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('household_profiles')->onDelete('cascade');
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->string('date_assessment')->nullable();
            $table->string('family_serial')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('age')->nullable();
            $table->string('age_group')->nullable();
            $table->string('sex')->nullable();
            
            $table->integer('current_smoker')->nullable();
            $table->integer('bti_ask')->nullable();
            $table->integer('bti_advise')->nullable();
            $table->integer('bti_assess')->nullable();
            $table->integer('bti_assist')->nullable();
            $table->integer('bti_arrange')->nullable();
            $table->integer('provided_bti')->nullable();
            $table->integer('binge_alcohol')->nullable();
            $table->integer('insufficient_pa')->nullable();
            $table->integer('unhealthy_diet')->nullable();
            $table->integer('bmi_category')->nullable();
            
            $table->string('screening_date1')->nullable();
            $table->string('screening_date2')->nullable();
            $table->integer('bp_systolic1')->nullable();
            $table->integer('bp_diastolic1')->nullable();
            $table->integer('bp_systolic2')->nullable();
            $table->integer('bp_diastolic2')->nullable();
            $table->integer('hypertension_result')->nullable();
            $table->integer('meds_initial')->nullable();
            $table->integer('meds_changed')->nullable();
            
            $table->json('monthly_meds')->nullable(); // MonthMed[] arrays

            $table->integer('diabetes_result')->nullable();
            $table->integer('antidiabetic_meds')->nullable();

            $table->json('monthly_diabetic_meds')->nullable(); // MonthMed[] arrays

            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('philpen_risk_assessments');
    }
};