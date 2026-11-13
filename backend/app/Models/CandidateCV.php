<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateCV extends Model
{
    use HasFactory;

    protected $table = 'candidate_cvs';
    // Tuyệt đối không đưa storage_key và sha256 vào mass-assignment để tránh bị ghi đè
    protected $fillable = ['candidate_profile_id', 'original_filename', 'mime_type', 'file_size', 'parse_status', 'uploaded_at', 'deleted_at'];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function candidateProfile()
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function extractions()
    {
        return $this->hasMany(CVExtraction::class, 'cv_id');
    }

    public function latestExtraction()
    {
        return $this->hasOne(CVExtraction::class, 'cv_id')->latestOfMany();
    }
}
