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
        Schema::create('cv_extraction_skills', function (Blueprint $table) {
            $table->foreignId('cv_extraction_id')->constrained('cv_extractions')->restrictOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->restrictOnDelete();
            $table->enum('source', ['detected', 'manual'])->default('detected');
            $table->enum('review_status', ['detected', 'confirmed', 'rejected'])->default('detected');

            // Bảng trung gian (Pivot) dùng khóa chính kép
            $table->primary(['cv_extraction_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_extraction_skills');
    }
};
