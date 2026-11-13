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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('title', 200);
            $table->longText('description');
            $table->longText('requirements_text');
            $table->foreignId('job_group_id')->constrained('job_groups')->restrictOnDelete();
            $table->foreignId('employment_type_id')->constrained('employment_types')->restrictOnDelete();
            $table->foreignId('work_mode_id')->constrained('work_modes')->restrictOnDelete();
            $table->foreignId('seniority_level_id')->constrained('seniority_levels')->restrictOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('publication_status', ['not_public', 'active', 'closed', 'expired', 'hidden'])->default('not_public');
            $table->text('rejection_reason')->nullable();
            $table->text('hidden_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            // Đánh index phục vụ tra cứu admin và public search
            $table->index(['company_id', 'review_status', 'publication_status', 'expires_at'], 'idx_jobs_admin_search');
            $table->index(['review_status', 'publication_status', 'expires_at'], 'idx_jobs_public_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
