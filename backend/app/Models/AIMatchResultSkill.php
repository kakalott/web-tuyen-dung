<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIMatchResultSkill extends Model
{
    use HasFactory;

    protected $table = 'ai_match_result_skills';
    public $timestamps = false;
    protected $fillable = ['match_result_id', 'skill_id', 'requirement_type', 'match_status'];

    public function matchResult() { return $this->belongsTo(AIMatchResult::class, 'match_result_id'); }
    public function skill() { return $this->belongsTo(Skill::class); }
}
