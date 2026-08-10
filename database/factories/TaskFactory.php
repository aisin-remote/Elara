<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'project_id' => Project::factory(),
            'status_id' => TaskStatus::factory(),
            'creator_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(),
            'priority' => TaskPriority::MEDIUM,
            'position' => 1024,
        ];
    }
}
