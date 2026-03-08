<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskGroup>
 */
class TaskGroupFactory extends Factory
{
  /**
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'project_id' => Project::factory(),
      'name' => fake()->randomElement(['To Do', 'In Progress', 'Review', 'Done', 'Backlog']),
      'description' => fake()->optional()->sentence(),
      'sort_order' => fake()->numberBetween(0, 10),
      'color' => fake()->hexColor(),
    ];
  }
}
