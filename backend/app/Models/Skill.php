<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'normalized_name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function aliases()
    {
        return $this->hasMany(SkillAlias::class);
    }
}
