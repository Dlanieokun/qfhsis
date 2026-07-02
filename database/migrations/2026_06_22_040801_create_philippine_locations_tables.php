<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->string('regCode')->primary();
            $table->string('regDesc');
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->string('provCode')->primary();
            $table->string('provDesc');
            $table->string('regCode');
        });

        Schema::create('municipalities', function (Blueprint $table) {
            $table->string('citymunCode')->primary();
            $table->string('citymunDesc');
            $table->string('provCode');
            $table->string('regCode'); // Note: some package data might have this, some might not.
        });

        Schema::create('barangays', function (Blueprint $table) {
            $table->string('brgyCode')->primary();
            $table->string('brgyDesc');
            $table->string('citymunCode');
            $table->string('provCode');
            $table->string('regCode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};