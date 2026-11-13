<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CVExtraction extends Model
{
    use HasFactory;

    protected $table = 'cv_extractions';
    protected $fillable = [
        'cv_id', 'extracted_text', 'normalized_text', 'confirmed_text', 
        'extraction_status', 'review_status', 'parser_version', 
        'warnings', 'extracted_at', 'confirmed_at'
    ];

    protected $casts = [
        'warnings' => 'json',
        'extracted_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function candidateCV()
    {
        return $this->belongsTo(CandidateCV::class, 'cv_id');
    }

    public function extractedSkills()
    {
        return $this->hasMany(CVExtractionSkill::class, 'cv_extraction_id');
    }
}
