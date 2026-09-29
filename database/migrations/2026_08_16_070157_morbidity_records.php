<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('morbidity_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Reporting period — split to match the Android entity's reportMonth/reportYear
            // fields exactly, instead of the single 'report_month' string this table had.
            $table->string('reportMonth', 20)->nullable();
            $table->string('reportYear', 4)->nullable();

            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('municipality')->nullable();
            $table->string('barangay')->nullable();

            // Disease name was missing entirely — without it, two diseases that share an
            // ICD code (e.g. A09.0's two rows on the DOH form) can't be told apart.
            $table->string('diseaseName')->nullable();
            $table->string('icdCode')->nullable();

            $table->integer('age0to6daysMale')->default(0);
            $table->integer('age0to6daysFemale')->default(0);
            $table->integer('age7to28daysMale')->default(0);
            $table->integer('age7to28daysFemale')->default(0);
            $table->integer('age29daysto11moMale')->default(0);
            $table->integer('age29daysto11moFemale')->default(0);
            $table->integer('age1to4yrsMale')->default(0);
            $table->integer('age1to4yrsFemale')->default(0);
            $table->integer('age5to9yrsMale')->default(0);
            $table->integer('age5to9yrsFemale')->default(0);
            $table->integer('age10to14yrsMale')->default(0);
            $table->integer('age10to14yrsFemale')->default(0);
            $table->integer('age15to19yrsMale')->default(0);
            $table->integer('age15to19yrsFemale')->default(0);
            $table->integer('age20to24yrsMale')->default(0);
            $table->integer('age20to24yrsFemale')->default(0);
            $table->integer('age25to29yrsMale')->default(0);
            $table->integer('age25to29yrsFemale')->default(0);
            $table->integer('age30to34yrsMale')->default(0);
            $table->integer('age30to34yrsFemale')->default(0);
            $table->integer('age35to39yrsMale')->default(0);
            $table->integer('age35to39yrsFemale')->default(0);
            $table->integer('age40to44yrsMale')->default(0);
            $table->integer('age40to44yrsFemale')->default(0);
            $table->integer('age45to49yrsMale')->default(0);
            $table->integer('age45to49yrsFemale')->default(0);
            $table->integer('age50to54yrsMale')->default(0);
            $table->integer('age50to54yrsFemale')->default(0);
            $table->integer('age55to59yrsMale')->default(0);
            $table->integer('age55to59yrsFemale')->default(0);
            $table->integer('age60plusMale')->default(0);
            $table->integer('age60plusFemale')->default(0);

            // isSynced is a device-local flag on the Android entity (whether this row has
            // reached the server); a row that exists here is by definition already synced,
            // so it's intentionally not duplicated on the server table.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('morbidity_records');
    }
};