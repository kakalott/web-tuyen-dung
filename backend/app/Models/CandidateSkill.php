<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateSkill extends Model
{
    use HasFactory;

    protected $table = 'candidate_skills';
    public $timestamps = false; // Bảng này không có created_at, updated_at
    protected $fillable = ['candidate_profile_id', 'skill_id', 'source', 'confirmed_at'];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];
}
