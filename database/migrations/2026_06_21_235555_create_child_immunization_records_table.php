<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_immunization_records', function (Blueprint $table) {
            $table->id();
            
            // Foreign link
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');

            // Demographics
            $table->string('registrationDate')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->string('childName')->nullable();
            $table->string('dateOfBirth')->nullable();
            $table->string('ageMonths')->nullable();
            $table->string('sex')->nullable();
            $table->string('motherName')->nullable();
            $table->string('address')->nullable();

            // CPAB
            $table->boolean('td2Mother')->default(false);
            $table->boolean('td3To5Mother')->default(false);

            // BCG
            $table->string('bcgWithin24hAge')->nullable();
            $table->string('bcgWithin24hDate')->nullable();
            $table->string('bcgLateAge')->nullable();
            $table->string('bcgLateDate')->nullable();

            // Hepatitis B
            $table->string('hepaBWithin24hAge')->nullable();
            $table->string('hepaBWithin24hDate')->nullable();
            $table->string('hepaBLateAge')->nullable();
            $table->string('hepaBLateDate')->nullable();

            // DPT-HiB-HepB
            $table->string('dpt1Age')->nullable();
            $table->string('dpt1Date')->nullable();
            $table->string('dpt2Age')->nullable();
            $table->string('dpt2Date')->nullable();
            $table->string('dpt3Age')->nullable();
            $table->string('dpt3Date')->nullable();

            // OPV
            $table->string('opv1Age')->nullable();
            $table->string('opv1Date')->nullable();
            $table->string('opv2Age')->nullable();
            $table->string('opv2Date')->nullable();
            $table->string('opv3Age')->nullable();
            $table->string('opv3Date')->nullable();

            // IPV
            $table->string('ipv1Age')->nullable();
            $table->string('ipv1Date')->nullable();
            $table->string('ipv2Age')->nullable();
            $table->string('ipv2Date')->nullable();

            // PCV
            $table->string('pcv1Age')->nullable();
            $table->string('pcv1Date')->nullable();
            $table->string('pcv2Age')->nullable();
            $table->string('pcv2Date')->nullable();
            $table->string('pcv3Age')->nullable();
            $table->string('pcv3Date')->nullable();

            // MMR
            $table->string('mmr1Age')->nullable();
            $table->string('mmr1Date')->nullable();
            $table->string('mmr2Age')->nullable();
            $table->string('mmr2Date')->nullable();

            // FIC
            $table->boolean('ficBcg')->default(false);
            $table->boolean('ficDpt3')->default(false);
            $table->boolean('ficOpv3')->default(false);
            $table->boolean('ficMmr2')->default(false);
            $table->string('ficDate')->nullable();

            // CIC
            $table->boolean('cicBcg')->default(false);
            $table->boolean('cicDpt3')->default(false);
            $table->boolean('cicOpv3')->default(false);
            $table->boolean('cicMmr2')->default(false);
            $table->string('cicDate')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();

            $table->text('remarks')->nullable();
            
            $table->timestamps(); // Optional: Adds created_at & updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_immunization_records');
    }
};