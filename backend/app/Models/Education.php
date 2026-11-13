<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Education extends Model
{
    use HasFactory;

    protected $table = 'educations';
    protected $fillable = ['candidate_profile_id', 'institution', 'degree', 'field_of_study', 'start_date', 'end_date', 'description'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
