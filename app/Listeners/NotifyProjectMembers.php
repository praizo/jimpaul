<?php

namespace App\Listeners;

use App\Events\ProjectCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyProjectMembers implements ShouldQueue
{
  use InteractsWithQueue;

  /**
   * The number of times the job may be attempted.
   */
  public int $tries = 3;

  /**
   * The number of seconds to wait before retrying the job.
   */
  public int $backoff = 60;

  /**
   * Handle the event.
   */
  public function handle(ProjectCreated $event): void
  {
    $project = $event->project;

    // Collect all unique assignees from tasks
    $assignees = $project->tasks
      ->whereNotNull('assigned_to')
      ->pluck('assignee')
      ->filter()
      ->unique('id');

    foreach ($assignees as $assignee) {
      Log::info('Notifying project member', [
        'project_id' => $project->id,
        'user_id' => $assignee->id,
        'email' => $assignee->email,
      ]);

      // In production: Mail::to($assignee)->send(new ProjectAssignedMail($project));
    }

    Log::info('All project members notified', [
      'project_id' => $project->id,
      'notified_count' => $assignees->count(),
    ]);
  }

  /**
   * Handle a job failure.
   */
  public function failed(ProjectCreated $event, \Throwable $exception): void
  {
    Log::error('Failed to notify project members', [
      'project_id' => $event->project->id,
      'error' => $exception->getMessage(),
    ]);
  }
}
