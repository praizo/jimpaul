<?php

namespace Database\Seeders;

use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $users = User::factory(10)->create();
        $users->push($testUser);

        $labels = Label::factory(10)->create();

        Project::factory(5)->recycle($users)->create()->each(function (Project $project) use ($users, $labels) {
            $taskGroups = TaskGroup::factory(4)->create(['project_id' => $project->id]);

            foreach ($taskGroups as $group) {
                Task::factory(fake()->numberBetween(3, 8))
                    ->recycle($users)
                    ->create([
                        'task_group_id' => $group->id,
                    ])->each(function (Task $task) use ($users, $labels, $group) {
                        $task->labels()->attach(
                            $labels->random(fake()->numberBetween(0, 3))->pluck('id')->toArray()
                        );

                        if (fake()->boolean(30)) {
                            $subtasks = Task::factory(fake()->numberBetween(1, 3))
                                ->recycle($users)
                                ->create([
                                    'task_group_id' => $group->id,
                                    'parent_id' => $task->id,
                                ]);

                            foreach ($subtasks as $subtask) {
                                $subtask->labels()->attach(
                                    $labels->random(fake()->numberBetween(0, 2))->pluck('id')->toArray()
                                );
                            }
                        }
                    });
            }
        });
    }
}
