<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

    protected $table = 'applications';
    // Tuyệt đối không cho phép mass-assignment cập nhật 'status', 'applied_at', 'withdrawn_at'
    protected $fillable = ['candidate_profile_id', 'job_id', 'cv_id'];

    protected $casts = [
        'applied_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function candidateProfile() { return $this->belongsTo(CandidateProfile::class); }
    public function job() { return $this->belongsTo(Job::class); }
    public function cv() { return $this->belongsTo(CandidateCV::class, 'cv_id'); }
    public function candidateCV() { return $this->belongsTo(CandidateCV::class, 'cv_id'); }
    public function statusHistories() { return $this->hasMany(ApplicationStatusHistory::class, 'application_id'); }
    public function histories() { return $this->hasMany(ApplicationStatusHistory::class, 'application_id'); }
    public function interviews() { return $this->hasMany(Interview::class, 'application_id'); }
    public function latestMatchResult()
    {
        return $this->hasOne(AIMatchResult::class, 'cv_id', 'cv_id')->latestOfMany();
    }
}
