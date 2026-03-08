<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Resources\V1\ProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
  public function __construct(
    private ProjectService $projectService
  ) {}

  /**
   * Create a new project with deeply nested task groups, tasks, subtasks, and labels.
   *
   * This endpoint demonstrates:
   * - Accepting deeply nested JSON payloads
   * - Complex input validation via Form Request
   * - Multiple DB operations inside a transaction (via Service layer)
   * - Event dispatching to trigger queued jobs
   * - Structured error/success JSON responses
   * - Layered architecture: Controller → Service → Repository → Model
   */
  public function store(StoreProjectRequest $request): JsonResponse
  {
    try {
      $project = $this->projectService->createProjectWithNestedData(
        validatedData: $request->validated(),
        userId: $request->user()->id,
      );

      return response()->json([
        'success' => true,
        'message' => 'Project created successfully.',
        'data' => new ProjectResource($project),
      ], 201);
    } catch (\Throwable $e) {
      // Transaction is automatically rolled back by DB::transaction()
      Log::error('Project creation failed', [
        'user_id' => $request->user()->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'An error occurred while creating the project. All changes have been rolled back.',
        'error' => config('app.debug') ? $e->getMessage() : 'Internal server error.',
      ], 500);
    }
  }
}
