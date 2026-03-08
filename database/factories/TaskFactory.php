<?php

namespace Database\Factories;

use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
  /**
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'task_group_id' => TaskGroup::factory(),
      'parent_id' => null,
      'assigned_to' => fake()->optional(0.7)->passthrough(User::factory()),
      'title' => fake()->sentence(4),
      'description' => fake()->optional()->paragraph(),
      'status' => fake()->randomElement(['pending', 'in_progress', 'review', 'completed', 'cancelled']),
      'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
      'sort_order' => fake()->numberBetween(0, 20),
      'estimated_hours' => fake()->optional()->numberBetween(1, 40),
      'due_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
      'completed_at' => null,
    ];
  }

  public function completed(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'completed',
      'completed_at' => now(),
    ]);
  }

  public function highPriority(): static
  {
    return $this->state(fn(array $attributes) => [
      'priority' => 'high',
    ]);
  }
}
