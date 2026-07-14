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
        Schema::create('prenatal_immunization_records', function (Blueprint $table) {
        $table->id();
        $table->foreignId('maternalRecordId')->constrained('maternal_care_records')->onDelete('cascade');
        $table->string('td1Date')->nullable();
        $table->string('td2Date')->nullable();
        $table->string('td3Date')->nullable();
        $table->string('td4Date')->nullable();
        $table->string('td5Date')->nullable();
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
        Schema::dropIfExists('prenatal_immunization_records');
    }
};
