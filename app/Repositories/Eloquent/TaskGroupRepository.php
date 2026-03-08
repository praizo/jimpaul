<?php

namespace App\Repositories\Eloquent;

use App\Models\TaskGroup;
use App\Repositories\Contracts\TaskGroupRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TaskGroupRepository implements TaskGroupRepositoryInterface
{
  public function __construct(
    private TaskGroup $model
  ) {}

  public function create(array $data): TaskGroup
  {
    return $this->model->newQuery()->create($data);
  }

  public function findByProject(int $projectId): Collection
  {
    return $this->model->newQuery()
      ->where('project_id', $projectId)
      ->orderBy('sort_order')
      ->get();
  }

  public function update(TaskGroup $taskGroup, array $data): TaskGroup
  {
    $taskGroup->update($data);

    return $taskGroup->fresh();
  }

  public function delete(TaskGroup $taskGroup): bool
  {
    return $taskGroup->delete();
  }
}
