<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobGroup;
use App\Models\EmploymentType;
use App\Models\WorkMode;
use App\Models\SeniorityLevel;
use App\Models\Skill;
use App\Models\SkillAlias;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Job Groups
        $groups = ['Backend Development', 'Frontend Development'];
        foreach ($groups as $group) {
            JobGroup::updateOrCreate(['name' => $group]);
        }

        // 2. Employment Types
        $types = ['Internship', 'Full-time'];
        foreach ($types as $type) {
            EmploymentType::updateOrCreate(['name' => $type]);
        }

        // 3. Work Modes
        $modes = ['On-site', 'Remote', 'Hybrid'];
        foreach ($modes as $mode) {
            WorkMode::updateOrCreate(['name' => $mode]);
        }

        // 4. Seniority Levels
        $levels = ['Intern', 'Fresher', 'Junior'];
        foreach ($levels as $level) {
            SeniorityLevel::updateOrCreate(['name' => $level]);
        }

        // 5. Skills
        $skills = [
            ['name' => 'PHP', 'normalized_name' => 'php'],
            ['name' => 'Laravel', 'normalized_name' => 'laravel'],
            ['name' => 'MySQL', 'normalized_name' => 'mysql'],
            ['name' => 'REST API', 'normalized_name' => 'rest api'],
            ['name' => 'Git', 'normalized_name' => 'git'],
            ['name' => 'JavaScript', 'normalized_name' => 'javascript'],
            ['name' => 'Vue.js', 'normalized_name' => 'vue.js'],
        ];

        foreach ($skills as $skillData) {
            Skill::updateOrCreate(
                ['normalized_name' => $skillData['normalized_name']],
                ['name' => $skillData['name'], 'is_active' => true]
            );
        }

        // 6. Skill Aliases
        $aliases = [
            'javascript' => ['alias' => 'JS', 'normalized_alias' => 'js'],
            'rest api' => ['alias' => 'RESTful API', 'normalized_alias' => 'restful api'],
        ];

        foreach ($aliases as $normSkillName => $aliasData) {
            $skill = Skill::where('normalized_name', $normSkillName)->first();
            if ($skill) {
                SkillAlias::updateOrCreate(
                    ['normalized_alias' => $aliasData['normalized_alias']],
                    ['skill_id' => $skill->id, 'alias' => $aliasData['alias'], 'is_active' => true]
                );
            }
        }
    }
}
