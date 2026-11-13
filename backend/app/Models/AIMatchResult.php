<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIMatchResult extends Model
{
    use HasFactory;

    protected $table = 'ai_match_results';
    public $timestamps = false;

    // Guard owner/status/PK from mass-assignment theo thiết kế
    protected $fillable = [
        'cv_id', 'cv_extraction_id', 'job_id', 'application_id',
        'baseline_score', 'semantic_similarity', 'skill_coverage', 'match_score',
        'required_count', 'matched_required_count',
        'algorithm_version', 'model_name', 'cv_text_hash', 'job_text_hash', 'input_fingerprint',
        'warnings', 'computed_at'
    ];

    protected $casts = [
        'warnings' => 'json',
        'computed_at' => 'datetime',
    ];

    public function candidateCV() { return $this->belongsTo(CandidateCV::class, 'cv_id'); }
    public function cvExtraction() { return $this->belongsTo(CVExtraction::class, 'cv_extraction_id'); }
    public function job() { return $this->belongsTo(Job::class); }
    public function application() { return $this->belongsTo(Application::class); }
    public function matchResultSkills() { return $this->hasMany(AIMatchResultSkill::class, 'match_result_id'); }
}
