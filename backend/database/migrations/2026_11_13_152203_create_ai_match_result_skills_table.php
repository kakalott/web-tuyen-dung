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
        Schema::create('ai_match_result_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_result_id')->constrained('ai_match_results')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->restrictOnDelete();
            $table->enum('requirement_type', ['required', 'preferred']);
            $table->enum('match_status', ['matched', 'missing']);

            $table->unique(['match_result_id', 'skill_id', 'requirement_type'], 'uq_match_skill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_match_result_skills');
    }
};
