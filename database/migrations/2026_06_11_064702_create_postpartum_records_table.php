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
        Schema::create('postpartum_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternalRecordId')->constrained('maternal_care_records')->onDelete('cascade');
            $table->string('visit24hDate')->nullable();
            $table->string('visit1wDate')->nullable();
            $table->string('visit2_4wDate')->nullable();
            $table->string('visit4_6wDate')->nullable();
            $table->string('classificationDate')->nullable();
            $table->string('PostpartumClassification')->nullable();
            $table->string('bpSys24h')->nullable();
            $table->string('bpDias24h')->nullable();
            $table->string('bpSys1w')->nullable();
            $table->string('bpDias1w')->nullable();
            $table->string('bpSys2_4w')->nullable();
            $table->string('bpDias2_4w')->nullable();
            $table->string('bpSys4_6w')->nullable();
            $table->string('bpDias4_6w')->nullable();
            $table->string('highBpGeneral')->nullable();
            $table->string('dangerSignsGeneral')->nullable();
            $table->string('referredGeneral')->nullable();
            $table->integer('dsBleeding')->nullable();
            $table->integer('dsVision')->nullable();
            $table->integer('dsAbdominal')->nullable();
            $table->integer('dsFever')->nullable();
            $table->integer('dsBreathing')->nullable();
            $table->string('referralDateGeneral')->nullable();
            $table->string('completedIfa')->nullable();
            $table->string('ifaCompletionDate')->nullable();
            $table->string('completedVitA')->nullable();
            $table->string('vitACompletionDate')->nullable();
            $table->string('breastfeedingInitiationDate')->nullable();
            $table->string('ironTabs1st')->nullable();
            $table->string('ironDate1st')->nullable();
            $table->string('ironTabs2nd')->nullable();
            $table->string('ironDate2nd')->nullable();
            $table->string('ironTabs3rd')->nullable();
            $table->string('ironDate3rd')->nullable();
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
        Schema::dropIfExists('postpartum_records');
    }
};
