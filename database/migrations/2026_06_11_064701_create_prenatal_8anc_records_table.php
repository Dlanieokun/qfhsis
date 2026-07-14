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
        Schema::create('prenatal_8anc_records', function (Blueprint $table) {
        $table->id();
        $table->foreignId('maternalRecordId')->constrained('maternal_care_records')->onDelete('cascade');
        
        // Generates loop for visit1Date/visit1Bp up to visit8
        for ($i = 1; $i <= 8; $i++) {
            $table->string("visit{$i}Date")->nullable();
            $table->string("visit{$i}Bp")->nullable();
        }
        
        $table->integer('completed8Anc')->nullable();
        $table->integer('highBp')->nullable();
        $table->integer('dangerSigns')->nullable();
        $table->text('dangerSignsDetail')->nullable();
        $table->integer('highBpReferred')->nullable();
        $table->string('dateReferred')->nullable();
        $table->string('classificationStatus')->nullable();
        $table->string('classificationDate')->nullable();
        $table->boolean('isSynced')->default(false);
        $table->boolean('newInsert')->default(true);
        $table->unsignedBigInteger('updatedAt')->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenatal_8anc_records');
    }
};
