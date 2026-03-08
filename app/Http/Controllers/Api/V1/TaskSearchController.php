<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\TaskSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskSearchController extends Controller
{
  public function __construct(
    private TaskSearchService $searchService
  ) {}

  /**
   * Search tasks by title/description.
   * Results are cached via the TaskSearchService for performance.
   *
   * GET /api/v1/tasks/search?q=design&per_page=10&page=1
   */
  public function __invoke(Request $request): JsonResponse
  {
    $request->validate([
      'q' => ['required', 'string', 'min:2', 'max:100'],
      'per_page' => ['sometimes', 'integer', 'min:5', 'max:50'],
    ]);

    $query = $request->input('q');
    $perPage = $request->integer('per_page', 15);
    $page = $request->integer('page', 1);

    $results = $this->searchService->search(
      query: $query,
      userId: $request->user()?->id,
      perPage: $perPage,
      page: $page,
    );

    return response()->json([
      'success' => true,
      'data' => $results->items(),
      'meta' => [
        'current_page' => $results->currentPage(),
        'last_page' => $results->lastPage(),
        'per_page' => $results->perPage(),
        'total' => $results->total(),
        'query' => $query,
      ],
    ]);
  }
}
