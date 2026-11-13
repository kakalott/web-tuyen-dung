<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CVExtractionSkill extends Model
{
    use HasFactory;

    protected $table = 'cv_extraction_skills';
    public $timestamps = false; // Bảng này không có created_at, updated_at
    protected $fillable = ['cv_extraction_id', 'skill_id', 'source', 'review_status'];
}
