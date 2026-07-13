<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('maternal_care_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->string('registrationDate')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->string('patientName')->nullable();
            $table->text('homeAddress')->nullable();
            $table->integer('age')->nullable();
            $table->string('ageGroup')->nullable();
            $table->string('birthDate')->nullable();
            $table->string('ImpDate')->nullable(); 
            $table->string('gravidaPara')->nullable();
            $table->string('eddDate')->nullable();
            $table->decimal('weightKg', 5, 2)->nullable(); 
            $table->decimal('heightCm', 5, 2)->nullable(); 
            $table->string('bmiValue')->nullable();
            $table->string('bmiStatus')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('maternal_care_records');
        Schema::enableForeignKeyConstraints();
    }
};