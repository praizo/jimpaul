<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
  /**
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'name' => $this->name,
      'description' => $this->description,
      'status' => $this->status,
      'priority' => $this->priority,
      'due_date' => $this->due_date?->toIso8601String(),
      'settings' => $this->settings,
      'task_groups' => TaskGroupResource::collection($this->whenLoaded('taskGroups')),
      'created_at' => $this->created_at->toIso8601String(),
      'updated_at' => $this->updated_at->toIso8601String(),
    ];
  }
}
