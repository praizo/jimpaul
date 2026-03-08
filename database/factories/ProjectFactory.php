<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
  /**
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'user_id' => User::factory(),
      'name' => fake()->sentence(3),
      'description' => fake()->paragraph(),
      'status' => fake()->randomElement(['draft', 'active', 'archived']),
      'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
      'due_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
      'settings' => [
        'notifications' => true,
        'auto_assign' => false,
      ],
    ];
  }

  public function active(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'active',
    ]);
  }

  public function archived(): static
  {
    return $this->state(fn(array $attributes) => [
      'status' => 'archived',
    ]);
  }
}
