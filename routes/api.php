<?php

use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskSearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and are assigned
| the "api" middleware group. They are prefixed with /api/v1.
|
*/

Route::prefix('v1')->group(function () {

  // Public search endpoint (optionally scoped to authenticated user)
  Route::get('/tasks/search', TaskSearchController::class)
    ->name('api.v1.tasks.search');

  // Authenticated endpoints
  Route::middleware('auth:sanctum')->group(function () {
    Route::post('/projects', [ProjectController::class, 'store'])
      ->name('api.v1.projects.store');
  });
});
