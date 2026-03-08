<?php

namespace App\Providers;

use App\Repositories\Contracts\LabelRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskGroupRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Eloquent\LabelRepository;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\TaskGroupRepository;
use App\Repositories\Eloquent\TaskRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
  /**
   * All repository bindings for the application.
   *
   * @var array<class-string, class-string>
   */
  public array $bindings = [
    ProjectRepositoryInterface::class => ProjectRepository::class,
    TaskGroupRepositoryInterface::class => TaskGroupRepository::class,
    TaskRepositoryInterface::class => TaskRepository::class,
    LabelRepositoryInterface::class => LabelRepository::class,
  ];

  public function register(): void
  {
    //
  }

  public function boot(): void
  {
    //
  }
}
