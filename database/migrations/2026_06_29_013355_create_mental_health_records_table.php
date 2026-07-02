<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mental_health_records', function (Blueprint $table) {
            // Room: @PrimaryKey(autoGenerate = true) int recordNo
            $table->id('recordNo'); 
            
            $table->string('dateOfAssessment')->nullable(); // mm/dd/yy
            $table->string('familySerialNumber')->nullable();
            $table->string('name')->nullable(); // LastName, FullName, MI
            $table->string('address')->nullable();
            $table->string('dateOfBirth')->nullable(); // mm/dd/yy
            $table->integer('age');
            $table->string('ageGroup', 10)->nullable(); // A/B/C/D
            $table->string('sex', 10)->nullable(); // M/F
            
            // Room: boolean screenedMhgap -> 1 for true, 0 for false
            $table->boolean('screenedMhgap')->default(false); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mental_health_records');
    }
};