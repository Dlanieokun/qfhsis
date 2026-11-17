<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('household_profiles', function (Blueprint $table) {
            // Resident, Trans-In, Trans-Out, Dead
            $table->string('status')->default('Resident')->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('household_profiles', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
