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
        Schema::create('ai_match_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained('candidate_cvs')->restrictOnDelete();
            $table->foreignId('cv_extraction_id')->constrained('cv_extractions')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            
            $table->decimal('baseline_score', 5, 2);
            $table->decimal('semantic_similarity', 7, 6);
            $table->decimal('skill_coverage', 7, 6)->nullable(); // Nullable khi JD không có required skills
            $table->decimal('match_score', 5, 2);
            
            $table->unsignedInteger('required_count')->default(0);
            $table->unsignedInteger('matched_required_count')->default(0);
            
            $table->string('algorithm_version', 80);
            $table->string('model_name', 200);
            $table->char('cv_text_hash', 64);
            $table->char('job_text_hash', 64);
            $table->char('input_fingerprint', 64); // Dùng để xác định kết quả bị cũ (stale)
            
            $table->json('warnings')->nullable();
            $table->dateTime('computed_at');

            $table->index(['job_id', 'cv_id', 'computed_at'], 'idx_match_job_cv_time');
            $table->index('application_id', 'idx_match_application');
            $table->index('input_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_match_results');
    }
};
