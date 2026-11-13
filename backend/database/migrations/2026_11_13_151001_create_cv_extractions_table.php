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
        Schema::create('cv_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_id')->constrained('candidate_cvs')->restrictOnDelete();
            $table->longText('extracted_text')->nullable();
            $table->longText('normalized_text')->nullable();
            $table->longText('confirmed_text')->nullable();
            $table->enum('extraction_status', ['success', 'failed'])->default('success');
            $table->enum('review_status', ['draft', 'confirmed'])->default('draft');
            $table->string('parser_version', 80)->nullable();
            $table->json('warnings')->nullable();
            $table->dateTime('extracted_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'cv_id']);
            $table->index(['cv_id', 'created_at']);
            $table->index('review_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cv_extractions');
    }
};
