<?php

namespace App\Repositories\Contracts;

use App\Models\Label;
use Illuminate\Database\Eloquent\Collection;

interface LabelRepositoryInterface
{
  public function create(array $data): Label;

  public function findOrCreateByName(string $name, string $color = '#6366f1'): Label;

  public function findAll(): Collection;
}
