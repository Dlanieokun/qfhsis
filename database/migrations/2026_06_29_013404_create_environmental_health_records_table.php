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
        Schema::create('environmental_health_records', function (Blueprint $table) {
            // Room: @PrimaryKey(autoGenerate = true) long id
            $table->id(); 
            
            $table->string('householdHeadName')->nullable();
            $table->foreignId('userId')->nullable()->constrained('users')->onDelete('cascade');

            // Section 1 - Water Source Booleans
            $table->boolean('waterLevelI')->default(false);
            $table->boolean('waterLevelII')->default(false);
            $table->boolean('waterLevelIII')->default(false);
            $table->string('waterSourceOthers')->nullable();

            // Section 1 - Operational Integer Flags/Booleans
            $table->boolean('waterLocatedInsideDwelling')->default(false);
            $table->boolean('waterAvailable12Hours')->default(false);
            $table->string('microbiologicalTestDate')->nullable();
            $table->tinyInteger('microbiologicalTestResult')->default(-1); // 1 = Positive, 0 = Negative, -1 = Unchecked
            $table->tinyInteger('waterSafetyPlanOperational')->default(-1); // 1 = Yes, 0 = No, -1 = Unchecked

            // Section 2 - Sanitation Properties
            $table->string('sanitationStatus')->nullable(); // "Functional Sanitary", "Unsanitary", "No Toilet"
            $table->tinyInteger('unsanitaryToiletType')->default(0); // 0, 1, 2, 3
            $table->tinyInteger('toiletShared')->default(0); // 1 = Yes, 0 = No
            $table->tinyInteger('basicSanitationFacility')->default(0); // 1 = Yes, 0 = No
            $table->string('disposalDate')->nullable();

            $table->boolean('disposalInSitu')->default(false);
            $table->boolean('disposalOffSiteDesludged')->default(false);
            $table->boolean('disposalOffSiteSewer')->default(false);

            $table->tinyInteger('safelyManagedSanitationService')->default(0); // 1 = Yes, 0 = No
            $table->tinyInteger('safelyManagedDrinkingWater')->default(0);     // 1 = Yes, 0 = No
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('environmental_health_records');
    }
};