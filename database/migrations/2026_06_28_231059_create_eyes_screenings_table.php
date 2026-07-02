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
        Schema::create('eyes_screenings', function (Blueprint $table) {
            $table->id();
            $table->integer('profile_id')->nullable()->index();
            $table->string('date_screening')->nullable();
            $table->string('family_serial')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('age')->nullable();
            $table->string('age_group')->nullable();
            $table->string('sex')->nullable();
            $table->integer('screened')->nullable();
            $table->string('eye_disease_code')->nullable();
            $table->string('date_referred')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('eyes_screenings');
    }
};
