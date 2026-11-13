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
}
