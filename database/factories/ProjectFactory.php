<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by'   => User::factory(),
            'name'         => fake()->words(3, true),
            'description'  => fake()->optional()->sentence(),
            'status'       => 'active',
        ];
    }
}
