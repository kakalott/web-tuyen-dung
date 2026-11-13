<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillAlias extends Model
{
    use HasFactory;

    protected $table = 'skill_aliases';
    protected $fillable = ['skill_id', 'alias', 'normalized_alias', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}
