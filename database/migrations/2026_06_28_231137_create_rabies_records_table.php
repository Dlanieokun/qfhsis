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
        Schema::create('rabies_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->integer('age')->nullable();
            $table->string('sex')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('address')->nullable();
            $table->string('birthdate')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('philhealth_no')->nullable();
            $table->string('weight_kg')->nullable();
            $table->string('blood_pressure')->nullable();
            
            $table->string('date_of_bite')->nullable();
            $table->string('time_of_bite')->nullable();
            $table->string('place_of_bite')->nullable();
            
            $table->boolean('injury_scratch')->default(false);
            $table->boolean('injury_abrasion')->default(false);
            $table->boolean('injury_laceration')->default(false);
            $table->boolean('injury_punctured')->default(false);
            $table->boolean('injury_avulsed')->default(false);
            $table->boolean('injury_others')->default(false);
            $table->string('injury_others_specify')->nullable();
            
            $table->string('wound_status')->nullable();
            $table->string('wound_washing')->nullable();
            $table->string('biting_animal')->nullable();
            $table->string('biting_animal_others_specify')->nullable();
            $table->string('ownership_status')->nullable();
            $table->string('animal_status_at_bite')->nullable();
            $table->string('animal_status_at_consult')->nullable();
            $table->string('animal_died_date')->nullable();
            $table->string('animal_vaccination')->nullable();
            $table->string('animal_vaccination_date')->nullable();
            
            $table->boolean('condition_epilepsy')->default(false);
            $table->boolean('condition_dm')->default(false);
            $table->boolean('condition_hypertension')->default(false);
            $table->boolean('condition_asthma')->default(false);
            $table->boolean('condition_alcoholic')->default(false);
            $table->boolean('condition_egg_allergy')->default(false);
            
            $table->string('pvrv_day0_date')->nullable();
            $table->string('pvrv_day0_batch')->nullable();
            $table->string('pvrv_day3_date')->nullable();
            $table->string('pvrv_day3_batch')->nullable();
            $table->string('pvrv_day7_date')->nullable();
            $table->string('pvrv_day7_batch')->nullable();
            $table->string('pvrv_day28_date')->nullable();
            $table->string('pvrv_day28_batch')->nullable();
            $table->string('pvrv_outcome')->nullable();
            
            $table->string('pcev_day0_date')->nullable();
            $table->string('pcev_day0_batch')->nullable();
            $table->string('pcev_day3_date')->nullable();
            $table->string('pcev_day3_batch')->nullable();
            $table->string('pcev_day7_date')->nullable();
            $table->string('pcev_day7_batch')->nullable();
            $table->string('pcev_day28_date')->nullable();
            $table->string('pcev_day28_batch')->nullable();
            $table->string('pcev_outcome')->nullable();
            
            $table->string('erig')->nullable();
            $table->string('hrig')->nullable();
            $table->string('tetanus_toxoid_date')->nullable();
            $table->string('ats_dose')->nullable();
            $table->string('ats_date')->nullable();
            $table->text('impression')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('rabies_records');
    }
};
