<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobSkill extends Model
{
    use HasFactory;

    protected $table = 'job_skills';
    public $timestamps = false; // Không dùng timestamps
    protected $fillable = ['job_id', 'skill_id', 'requirement_type'];

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}
