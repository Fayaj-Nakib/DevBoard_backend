<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id'  => Project::factory(),
            'created_by'  => User::factory(),
            'assignee_id' => null,
            'title'       => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status'      => fake()->randomElement(['todo', 'in_progress', 'in_review', 'done']),
            'priority'    => fake()->randomElement(['low', 'medium', 'high']),
            'position'    => fake()->numberBetween(1, 100),
            'due_date'    => fake()->optional()->dateTimeBetween('now', '+30 days'),
        ];
    }
}
