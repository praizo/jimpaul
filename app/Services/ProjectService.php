<?php

namespace App\Services;

use App\Events\ProjectCreated;
use App\Models\Project;
use App\Repositories\Contracts\LabelRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskGroupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectService
{
  public function __construct(
    private ProjectRepositoryInterface $projectRepository,
    private TaskGroupRepositoryInterface $taskGroupRepository,
    private TaskRepositoryInterface $taskRepository,
    private LabelRepositoryInterface $labelRepository,
  ) {}

  /**
   * Create a project with deeply nested task groups, tasks, subtasks, and labels.
   * All operations are wrapped in a database transaction for atomicity.
   *
   * @param  array<string, mixed>  $validatedData
   *
   * @throws \Throwable
   */
  public function createProjectWithNestedData(array $validatedData, int $userId): Project
  {
    return DB::transaction(function () use ($validatedData, $userId) {
      // 1. Create the project
      $project = $this->projectRepository->create([
        'user_id' => $userId,
        'name' => $validatedData['name'],
        'description' => $validatedData['description'] ?? null,
        'status' => $validatedData['status'] ?? 'draft',
        'priority' => $validatedData['priority'] ?? 'medium',
        'due_date' => $validatedData['due_date'] ?? null,
        'settings' => $validatedData['settings'] ?? null,
      ]);

      // 2. Create task groups with their tasks
      if (! empty($validatedData['task_groups'])) {
        $this->createTaskGroups($project, $validatedData['task_groups']);
      }

      // 3. Reload project with all nested relations
      $project = $this->projectRepository->findByIdWithRelations($project->id, [
        'taskGroups.tasks.subtasks',
        'taskGroups.tasks.labels',
        'taskGroups.tasks.assignee',
      ]);

      // 4. Dispatch event (listeners handle async notifications via queue)
      ProjectCreated::dispatch($project);

      Log::info('Project created successfully', [
        'project_id' => $project->id,
        'user_id' => $userId,
        'task_groups_count' => $project->taskGroups->count(),
        'total_tasks' => $project->taskGroups->sum(fn($group) => $group->tasks->count()),
      ]);

      return $project;
    });
  }

  /**
   * Create task groups for a project.
   *
   * @param  array<int, array<string, mixed>>  $taskGroupsData
   */
  private function createTaskGroups(Project $project, array $taskGroupsData): void
  {
    foreach ($taskGroupsData as $sortOrder => $groupData) {
      $taskGroup = $this->taskGroupRepository->create([
        'project_id' => $project->id,
        'name' => $groupData['name'],
        'description' => $groupData['description'] ?? null,
        'sort_order' => $groupData['sort_order'] ?? $sortOrder,
        'color' => $groupData['color'] ?? null,
      ]);

      if (! empty($groupData['tasks'])) {
        $this->createTasks($taskGroup->id, $groupData['tasks']);
      }
    }
  }

  /**
   * Recursively create tasks and their subtasks.
   *
   * @param  array<int, array<string, mixed>>  $tasksData
   */
  private function createTasks(int $taskGroupId, array $tasksData, ?int $parentId = null): void
  {
    foreach ($tasksData as $sortOrder => $taskData) {
      $task = $this->taskRepository->create([
        'task_group_id' => $taskGroupId,
        'parent_id' => $parentId,
        'assigned_to' => $taskData['assigned_to'] ?? null,
        'title' => $taskData['title'],
        'description' => $taskData['description'] ?? null,
        'status' => $taskData['status'] ?? 'pending',
        'priority' => $taskData['priority'] ?? 'medium',
        'sort_order' => $taskData['sort_order'] ?? $sortOrder,
        'estimated_hours' => $taskData['estimated_hours'] ?? null,
        'due_date' => $taskData['due_date'] ?? null,
      ]);

      // Attach labels (find or create by name)
      if (! empty($taskData['labels'])) {
        $labelIds = collect($taskData['labels'])->map(function (array $labelData) {
          $label = $this->labelRepository->findOrCreateByName(
            $labelData['name'],
            $labelData['color'] ?? '#6366f1'
          );

          return $label->id;
        })->all();

        $task->labels()->sync($labelIds);
      }

      // Recursively create subtasks
      if (! empty($taskData['subtasks'])) {
        $this->createTasks($taskGroupId, $taskData['subtasks'], $task->id);
      }
    }
  }
}
