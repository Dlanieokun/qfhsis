<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent MySQL constraint checks from throwing 1824 errors
        Schema::disableForeignKeyConstraints();

        Schema::create('family_planning_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profileId')->constrained('household_profiles')->onDelete('cascade');
            $table->string('registrationDate')->nullable();
            $table->string('familySerialNumber')->nullable();
            $table->text('address')->nullable();
            $table->integer('age')->nullable();
            $table->string('birthDate')->nullable();
            $table->string('ageGroupCategory')->nullable();
            $table->string('clientType')->nullable();
            $table->string('commoditySource')->nullable();
            $table->string('previousMethod')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('family_planning_records');
        Schema::enableForeignKeyConstraints();
    }
};