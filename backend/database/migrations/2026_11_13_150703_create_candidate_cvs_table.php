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
        Schema::create('candidate_cvs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained('candidate_profiles')->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('storage_key', 500)->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->enum('parse_status', ['uploaded', 'processing', 'processed', 'failed'])->default('uploaded');
            $table->dateTime('uploaded_at');
            $table->dateTime('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['id', 'candidate_profile_id']);
            $table->index(['candidate_profile_id', 'uploaded_at']);
            $table->index('sha256');
            $table->index('parse_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_cvs');
    }
};
