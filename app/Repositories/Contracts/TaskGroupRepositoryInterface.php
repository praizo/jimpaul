<?php

namespace App\Repositories\Contracts;

use App\Models\TaskGroup;
use Illuminate\Database\Eloquent\Collection;

interface TaskGroupRepositoryInterface
{
  public function create(array $data): TaskGroup;

  public function findByProject(int $projectId): Collection;

  public function update(TaskGroup $taskGroup, array $data): TaskGroup;

  public function delete(TaskGroup $taskGroup): bool;
}
