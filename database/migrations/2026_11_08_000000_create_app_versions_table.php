<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_name', 50)->unique();
            // Path relative to public/, e.g. "downloads/qfhsis.apk".
            $table->string('file_path');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum', 64)->nullable(); // sha256 of the APK
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
