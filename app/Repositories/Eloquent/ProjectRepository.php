<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
  public function __construct(
    private Project $model
  ) {}

  public function create(array $data): Project
  {
    return $this->model->newQuery()->create($data);
  }

  public function findById(int $id): ?Project
  {
    return $this->model->newQuery()->find($id);
  }

  public function findByIdWithRelations(int $id, array $relations = []): ?Project
  {
    return $this->model->newQuery()
      ->with($relations)
      ->find($id);
  }

  public function findByUser(int $userId): Collection
  {
    return $this->model->newQuery()
      ->where('user_id', $userId)
      ->orderByDesc('updated_at')
      ->get();
  }

  public function update(Project $project, array $data): Project
  {
    $project->update($data);

    return $project->fresh();
  }

  public function delete(Project $project): bool
  {
    return $project->delete();
  }
}
