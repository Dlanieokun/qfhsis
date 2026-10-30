<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_workforce_data', function (Blueprint $table) {
            $table->id();
            $table->string('year', 4);
            $table->string('region_code', 20)->default('');
            $table->string('province_code', 20)->default('');
            $table->string('municipality_code', 20)->default('');
            // Order-independent fingerprint of the selected barangays (sha1 of the sorted codes).
            $table->string('barangay_key', 40);
            $table->json('barangay_codes')->nullable();
            // { "<indicator key>": { male, female, total, remarks } }
            $table->json('data');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['year', 'region_code', 'province_code', 'municipality_code', 'barangay_key'],
                'facility_workforce_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_workforce_data');
    }
};
