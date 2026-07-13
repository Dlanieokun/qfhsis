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
        Schema::create('geriatric_screening_records', function (Blueprint $table) {
            $table->id('record_no'); // Setting PK to match entity
            $table->foreignId('userId')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->string('date_of_screening')->nullable();
            $table->string('family_serial_number')->nullable();
            $table->string('name')->nullable();
            $table->string('address')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->integer('age')->nullable();
            $table->string('sex')->nullable();
            $table->string('results')->nullable(); // Stored as comma-separated
            
            $table->boolean('care_plan_provided')->default(false);
            $table->boolean('ppv_received_at60')->default(false);
            
            $table->string('ppv_date_given')->nullable();
            $table->string('influenza_date_given')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('geriatric_screening_records');
    }
};
