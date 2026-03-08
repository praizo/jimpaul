<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
  public function create(array $data): Project;

  public function findById(int $id): ?Project;

  public function findByIdWithRelations(int $id, array $relations = []): ?Project;

  public function findByUser(int $userId): Collection;

  public function update(Project $project, array $data): Project;

  public function delete(Project $project): bool;
}
