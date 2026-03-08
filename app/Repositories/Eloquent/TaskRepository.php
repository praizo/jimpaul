<?php

namespace App\Repositories\Eloquent;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository implements TaskRepositoryInterface
{
  public function __construct(
    private Task $model
  ) {}

  public function create(array $data): Task
  {
    return $this->model->newQuery()->create($data);
  }

  public function findById(int $id): ?Task
  {
    return $this->model->newQuery()->find($id);
  }

  public function findByTaskGroup(int $taskGroupId): Collection
  {
    return $this->model->newQuery()
      ->where('task_group_id', $taskGroupId)
      ->orderBy('sort_order')
      ->get();
  }

  public function update(Task $task, array $data): Task
  {
    $task->update($data);

    return $task->fresh();
  }

  public function delete(Task $task): bool
  {
    return $task->delete();
  }

  /**
   * Search tasks by title/description using LIKE (SQLite-compatible).
   * In production with MySQL, this would use MATCH AGAINST for full-text search.
   */
  public function search(string $query, ?int $userId = null, int $perPage = 15): LengthAwarePaginator
  {
    $searchQuery = $this->model->newQuery()
      ->with(['taskGroup.project', 'labels', 'assignee'])
      ->where(function ($q) use ($query) {
        $q->where('title', 'LIKE', "%{$query}%")
          ->orWhere('description', 'LIKE', "%{$query}%");
      });

    if ($userId) {
      $searchQuery->whereHas('taskGroup.project', function ($q) use ($userId) {
        $q->where('user_id', $userId);
      });
    }

    return $searchQuery
      ->orderByDesc('updated_at')
      ->paginate($perPage);
  }
}
