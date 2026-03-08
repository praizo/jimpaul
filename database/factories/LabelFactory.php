<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Label>
 */
class LabelFactory extends Factory
{
  /**
   * @return array<string, mixed>
   */
  public function definition(): array
  {
    return [
      'name' => fake()->unique()->randomElement([
        'bug',
        'feature',
        'enhancement',
        'documentation',
        'urgent',
        'design',
        'backend',
        'frontend',
        'testing',
        'devops',
        'refactor',
        'research',
      ]),
      'color' => fake()->hexColor(),
    ];
  }
}
