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
        Schema::create('intrapartum_records', function (Blueprint $table) {
        $table->id();
        $table->foreignId('maternalRecordId')->constrained('maternal_care_records')->onDelete('cascade');
        $table->string('deliveryOutcome')->nullable();
        $table->string('deliveryType')->nullable();
        $table->string('sex')->nullable();
        $table->string('birthWeight')->nullable();
        $table->string('weightClassification')->nullable();
        $table->string('placeOfDelivery')->nullable();
        $table->string('attendantAtBirth')->nullable();
        $table->string('deliveryDate')->nullable();
        $table->string('deliveryTime')->nullable();
        $table->text('remarks')->nullable();
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
        Schema::dropIfExists('intrapartum_records');
    }
};
