<?php

namespace Database\Factories;

use App\Enums\TaskStatusCategory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'category' => TaskStatusCategory::TODO,
            'position' => 1024,
            'is_system' => false,
        ];
    }
}
