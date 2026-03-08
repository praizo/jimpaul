<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
  /**
   * @return array<string, mixed>
   */
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'title' => $this->title,
      'description' => $this->description,
      'status' => $this->status,
      'priority' => $this->priority,
      'sort_order' => $this->sort_order,
      'estimated_hours' => $this->estimated_hours,
      'due_date' => $this->due_date?->toIso8601String(),
      'completed_at' => $this->completed_at?->toIso8601String(),
      'assignee' => $this->whenLoaded('assignee', fn() => [
        'id' => $this->assignee->id,
        'name' => $this->assignee->name,
        'email' => $this->assignee->email,
      ]),
      'labels' => $this->whenLoaded('labels', fn() => $this->labels->map(fn($label) => [
        'id' => $label->id,
        'name' => $label->name,
        'color' => $label->color,
      ])),
      'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
      'created_at' => $this->created_at->toIso8601String(),
      'updated_at' => $this->updated_at->toIso8601String(),
    ];
  }
}
