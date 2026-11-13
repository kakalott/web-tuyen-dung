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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained('candidate_profiles')->restrictOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->restrictOnDelete();
            $table->foreignId('cv_id')->constrained('candidate_cvs')->restrictOnDelete();
            
            // 6 trạng thái ứng tuyển độc lập
            $table->enum('status', ['pending', 'reviewing', 'interview', 'passed', 'rejected', 'withdrawn'])->default('pending');
            $table->dateTime('applied_at');
            $table->dateTime('withdrawn_at')->nullable();
            $table->timestamps();

            // Ràng buộc 1 ứng viên nộp 1 lần cho 1 tin
            $table->unique(['candidate_profile_id', 'job_id'], 'uq_application_candidate_job');
            
            // Indexes phục vụ lọc
            $table->index(['job_id', 'status', 'applied_at'], 'idx_application_job_status');
            $table->index(['candidate_profile_id', 'applied_at'], 'idx_application_candidate_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
