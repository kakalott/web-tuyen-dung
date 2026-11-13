<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateProfile extends Model
{
    use HasFactory;

    protected $table = 'candidate_profiles';
    protected $fillable = ['user_id', 'summary', 'phone', 'location'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function educations()
    {
        return $this->hasMany(Education::class);
    }

    public function experiences()
    {
        return $this->hasMany(Experience::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills', 'candidate_profile_id', 'skill_id')
            ->withPivot(['source', 'confirmed_at']);
    }
}
