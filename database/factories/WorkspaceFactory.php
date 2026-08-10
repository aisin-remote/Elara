<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->company().' Workspace',
            'icon' => null,
            'timezone' => 'UTC',
            'locale' => 'en',
            'settings_json' => ['week_start' => 1],
        ];
    }
}
