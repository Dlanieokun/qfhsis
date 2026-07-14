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
        Schema::create('prenatal_supplementation_records', function (Blueprint $table) {
            $table->id(); // auto-incrementing primary key (matches Room's autoGenerate = true)
            $table->unsignedBigInteger('maternal_record_id'); // Foreign structural relational reference

            // Deworming Status parameters tracking
            $table->boolean('received_deworming')->default(false);
            $table->string('deworming_date')->nullable();

            // Section 2: Iron Folic Acid (IFA) Tablets (#) and Dates (d)
            $table->string('ifa_v1_num')->nullable();
            $table->string('ifa_v1_date')->nullable();
            $table->string('ifa_v2_num')->nullable();
            $table->string('ifa_v2_date')->nullable();
            $table->string('ifa_v3_num')->nullable();
            $table->string('ifa_v3_date')->nullable();
            $table->string('ifa_v4_num')->nullable();
            $table->string('ifa_v4_date')->nullable();
            $table->string('ifa_v5_num')->nullable();
            $table->string('ifa_v5_date')->nullable();
            $table->string('ifa_v6_num')->nullable();
            $table->string('ifa_v6_date')->nullable();
            $table->boolean('completed_ifa')->default(false);
            $table->string('ifa_completed_date')->nullable();

            // Section 3: Multiple Micronutrients (MM) Capsules (#) and Dates (d)
            $table->string('mm_v1_num')->nullable();
            $table->string('mm_v1_date')->nullable();
            $table->string('mm_v2_num')->nullable();
            $table->string('mm_v2_date')->nullable();
            $table->string('mm_v3_num')->nullable();
            $table->string('mm_v3_date')->nullable();
            $table->string('mm_v4_num')->nullable();
            $table->string('mm_v4_date')->nullable();
            $table->string('mm_v5_num')->nullable();
            $table->string('mm_v5_date')->nullable();
            $table->string('mm_v6_num')->nullable();
            $table->string('mm_v6_date')->nullable();
            $table->boolean('completed_mm')->default(false);
            $table->string('mm_completed_date')->nullable();

            // Section 4: Calcium Carbonate (CC) Tablets (#) and Dates (d)
            $table->string('cc_v2_num')->nullable();
            $table->string('cc_v2_date')->nullable();
            $table->string('cc_v3_num')->nullable();
            $table->string('cc_v3_date')->nullable();
            $table->string('cc_v4_num')->nullable();
            $table->string('cc_v4_date')->nullable();
            $table->boolean('completed_cc')->default(false);
            $table->string('cc_completed_date')->nullable();
            $table->boolean('isSynced')->default(false);
            $table->boolean('newInsert')->default(true);
            $table->unsignedBigInteger('updatedAt')->nullable();

            // Standard Laravel timestamp tracking blueprint mapping
            $table->timestamps(); 
            
            // Optional: Foreign key constraint if maternal_care_records table exists
            // $table->foreign('maternal_record_id')->references('id')->on('maternal_care_records')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenatal_supplementation_records');
    }
};