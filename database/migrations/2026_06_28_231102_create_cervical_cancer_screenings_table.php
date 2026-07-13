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
        Schema::create('cervical_cancer_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('household_profiles')->onDelete('cascade');
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->string('date_assessment')->nullable();
            $table->string('family_serial')->nullable();
            $table->string('client_name')->nullable(); // Mapped from Room entity's @ColumnInfo
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('age')->nullable();
            
            $table->integer('cervical_screening_done')->nullable();
            $table->integer('cervical_result')->nullable();
            $table->integer('cervical_linked_to_care')->nullable();
            
            $table->integer('breast_risk_assessment')->nullable();
            $table->string('breast_age_risk_class')->nullable();
            $table->string('breast_exam_type')->nullable();
            $table->integer('breast_result')->nullable();
            $table->integer('breast_linked_to_care')->nullable();
            
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cervical_cancer_screenings');
    }
};
