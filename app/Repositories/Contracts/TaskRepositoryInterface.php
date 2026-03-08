<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
  public function create(array $data): Task;

  public function findById(int $id): ?Task;

  public function findByTaskGroup(int $taskGroupId): Collection;

  public function update(Task $task, array $data): Task;

  public function delete(Task $task): bool;

  /**
   * Search tasks by title/description with full-text matching.
   */
  public function search(string $query, ?int $userId = null, int $perPage = 15): LengthAwarePaginator;
}
