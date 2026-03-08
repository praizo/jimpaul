<?php

namespace App\Repositories\Eloquent;

use App\Models\Label;
use App\Repositories\Contracts\LabelRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class LabelRepository implements LabelRepositoryInterface
{
  public function __construct(
    private Label $model
  ) {}

  public function create(array $data): Label
  {
    return $this->model->newQuery()->create($data);
  }

  public function findOrCreateByName(string $name, string $color = '#6366f1'): Label
  {
    return $this->model->newQuery()->firstOrCreate(
      ['name' => strtolower(trim($name))],
      ['color' => $color]
    );
  }

  public function findAll(): Collection
  {
    return $this->model->newQuery()->orderBy('name')->get();
  }
}
