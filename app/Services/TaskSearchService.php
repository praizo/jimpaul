<?php

namespace App\Services;

use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class TaskSearchService
{
  private const CACHE_TTL_SECONDS = 300; // 5 minutes

  public function __construct(
    private TaskRepositoryInterface $taskRepository,
  ) {}

  /**
   * Search tasks with Redis caching layer.
   * Cache key is scoped per user and per query to prevent data leakage.
   */
  public function search(string $query, ?int $userId = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
  {
    $cacheKey = $this->buildCacheKey($query, $userId, $perPage, $page);

    return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($query, $userId, $perPage) {
      return $this->taskRepository->search($query, $userId, $perPage);
    });
  }

  /**
   * Invalidate search cache for a specific user (e.g., after task creation).
   */
  public function invalidateUserCache(int $userId): void
  {
    Cache::flush(); // In production, use tagged caching: Cache::tags(["user:{$userId}:search"])->flush()
  }

  /**
   * Build a deterministic, scoped cache key.
   */
  private function buildCacheKey(string $query, ?int $userId, int $perPage, int $page): string
  {
    return sprintf(
      'task_search:%s:%s:%d:%d',
      $userId ?? 'global',
      md5(strtolower(trim($query))),
      $perPage,
      $page
    );
  }
}
