<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Experience extends Model
{
    use HasFactory;

    protected $table = 'experiences';
    protected $fillable = ['candidate_profile_id', 'employer_name', 'job_title', 'start_date', 'end_date', 'description'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
