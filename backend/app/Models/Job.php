<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    use HasFactory;

    protected $table = 'jobs';
    // Ẩn review_status, publication_status, rejection_reason, hidden_reason, reviewed_by khỏi mass-assignment
    protected $fillable = [
        'company_id', 'title', 'description', 'requirements_text', 
        'job_group_id', 'employment_type_id', 'work_mode_id', 
        'seniority_level_id', 'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jobSkills()
    {
        return $this->hasMany(JobSkill::class, 'job_id');
    }

    // Các quan hệ tới bảng danh mục
    public function jobGroup() { return $this->belongsTo(JobGroup::class); }
    public function employmentType() { return $this->belongsTo(EmploymentType::class); }
    public function workMode() { return $this->belongsTo(WorkMode::class); }
    public function seniorityLevel() { return $this->belongsTo(SeniorityLevel::class); }
}
