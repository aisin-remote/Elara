<?php

namespace Database\Factories;

use App\Models\ScheduleEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleEventFactory extends Factory
{
    protected $model = ScheduleEvent::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+2 weeks');

        return [
            'workspace_id' => Workspace::factory(),
            'creator_id' => User::factory(),
            'title' => fake()->sentence(3),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+1 hour'),
            'timezone' => 'UTC',
            'color' => '#6366f1',
        ];
    }
}
