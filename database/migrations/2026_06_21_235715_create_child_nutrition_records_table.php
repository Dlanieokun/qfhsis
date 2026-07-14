<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_nutrition_records', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');

            // Section 1: Client Identification
            $table->string('dateRegistration')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->string('childName')->nullable();
            $table->string('dateOfBirth')->nullable();
            $table->string('ageMonths')->nullable();
            $table->string('sex')->nullable();
            $table->string('motherName')->nullable();
            $table->string('address')->nullable();

            // Section 2: Newborn Assessment
            $table->string('lengthAtBirth')->nullable();
            $table->string('weightAtBirth')->nullable();
            $table->string('birthWeightStatus')->nullable();
            $table->string('breastfeedingDate')->nullable();
            $table->string('placeOfDelivery')->nullable();

            // Section 3: Iron Supplementation
            $table->string('iron1Month')->nullable();
            $table->string('iron2Months')->nullable();
            $table->string('iron3Months')->nullable();
            $table->integer('ironCompleted')->default(0);
            $table->string('ironCompletedDate')->nullable();

            // Section 4: Vitamin A Supplementation
            $table->string('vitaA6to11')->nullable();
            $table->string('vitaA200Y1D1')->nullable();
            $table->string('vitaA200Y1D2')->nullable();
            $table->string('vitaA200Y2D1')->nullable();
            $table->string('vitaA200Y2D2')->nullable();
            $table->string('vitaA200Y3D1')->nullable();
            $table->string('vitaA200Y3D2')->nullable();
            $table->string('vitaA200Y4D1')->nullable();
            $table->string('vitaA200Y4D2')->nullable();

            // Section 5: MNP Supplementation
            $table->string('mnp6to11Provided')->nullable();
            $table->string('mnp6to11Completed')->nullable();
            $table->string('mnp6to11Remarks')->nullable();
            $table->string('mnp12to23Provided')->nullable();
            $table->string('mnp12to23Completed')->nullable();
            $table->string('mnp12to23Remarks')->nullable();

            // Section 6: LNS-SQ Supplementation
            $table->string('lns6to11Provided')->nullable();
            $table->string('lns6to11Completed')->nullable();
            $table->string('lns6to11Remarks')->nullable();
            $table->string('lns12to23Provided')->nullable();
            $table->string('lns12to23Completed')->nullable();
            $table->string('lns12to23Remarks')->nullable();

            // Section 7: MAM (SFP)
            $table->integer('mamIdentified')->default(0);
            $table->integer('mamEnrolled')->default(0);
            $table->integer('mamCured')->default(0);
            $table->integer('mamNonCured')->default(0);
            $table->integer('mamDefaulted')->default(0);
            $table->integer('mamDied')->default(0);

            // Section 8: SAM (OTC)
            $table->integer('samIdentified')->default(0);
            $table->integer('samAdmitted')->default(0);
            $table->integer('samCured')->default(0);
            $table->integer('samNonCured')->default(0);
            $table->integer('samDefaulted')->default(0);
            $table->integer('samDied')->default(0);

            // Section 9: Remarks
            $table->text('remarks')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_nutrition_records');
    }
};