<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'workspace_id' => Workspace::factory(),
            'owner_id' => User::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'status' => ProjectStatus::PLANNED,
            'start_date' => $startDate,
            'due_date' => fake()->dateTimeBetween($startDate, '+3 months'),
        ];
    }
}
