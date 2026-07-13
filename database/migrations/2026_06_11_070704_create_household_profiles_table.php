<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_profiles', function (Blueprint $table) {
            $table->id(); 
            $table->string('sitio')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->string('region')->nullable();
            $table->string('hhNumber')->nullable();
            $table->string('respondent')->nullable();
            $table->string('socioStatus')->nullable();
            $table->string('waterSource')->nullable();
            $table->string('toiletType')->nullable();
            $table->string('familyNumber')->nullable();
            $table->string('memberLastName')->nullable();
            $table->string('memberMiddleName')->nullable();
            $table->string('memberFirstName')->nullable();
            $table->string('relationship')->nullable();
            $table->string('sex')->nullable();
            $table->string('dob')->nullable();
            $table->string('philhealthId')->nullable();
            $table->string('philType')->nullable();
            $table->string('philCategory')->nullable();
            $table->integer('hpn')->nullable();
            $table->integer('dm')->nullable();
            $table->integer('tb')->nullable();
            $table->integer('fpMethod')->nullable();
            $table->string('fpMethodUsed')->nullable();
            $table->string('education')->nullable();
            $table->string('religion')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->unsignedBigInteger('updatedAt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('household_profiles', function (Blueprint $table) {
            $table->dropColumn(['isSynced', 'updatedAt']);
        });
    }
};