<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maternal_deaths', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->default(0)->index();
            $table->string('date_of_registration')->nullable();   // mm/dd/yyyy
            $table->string('full_name')->nullable();              // LastName, FirstName, MI
            $table->string('complete_address')->nullable();
            $table->integer('age')->default(0);                   // age in years
            $table->string('age_group')->nullable();              // A (10-14), B (15-19), C (20-49)
            $table->string('place_of_occurrence')->nullable();    // A (Resident), B (Non-Resident)
            $table->string('cause_of_death')->nullable();         // A (Direct), B (Indirect)
            $table->text('remarks')->nullable();
            $table->boolean('synced')->default(false);
            $table->bigInteger('sync_timestamp')->default(0);
            $table->timestamps();

            $table->foreign('profile_id')
                  ->references('id')
                  ->on('household_profiles')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternal_deaths');
    }
};