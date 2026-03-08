<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProjectRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Authorization handled via Sanctum middleware
  }

  /**
   * Complex nested validation rules for deeply nested project creation.
   *
   * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
   */
  public function rules(): array
  {
    return [
      // Project root
      'name' => ['required', 'string', 'max:255'],
      'description' => ['nullable', 'string', 'max:5000'],
      'status' => ['sometimes', 'string', 'in:draft,active,archived'],
      'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
      'due_date' => ['nullable', 'date', 'after:today'],
      'settings' => ['nullable', 'array'],
      'settings.notifications' => ['sometimes', 'boolean'],
      'settings.auto_assign' => ['sometimes', 'boolean'],

      // Task groups (nested level 1)
      'task_groups' => ['sometimes', 'array', 'max:20'],
      'task_groups.*.name' => ['required', 'string', 'max:255'],
      'task_groups.*.description' => ['nullable', 'string', 'max:2000'],
      'task_groups.*.sort_order' => ['sometimes', 'integer', 'min:0'],
      'task_groups.*.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

      // Tasks within task groups (nested level 2)
      'task_groups.*.tasks' => ['sometimes', 'array', 'max:50'],
      'task_groups.*.tasks.*.title' => ['required', 'string', 'max:255'],
      'task_groups.*.tasks.*.description' => ['nullable', 'string', 'max:5000'],
      'task_groups.*.tasks.*.status' => ['sometimes', 'string', 'in:pending,in_progress,review,completed,cancelled'],
      'task_groups.*.tasks.*.priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
      'task_groups.*.tasks.*.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
      'task_groups.*.tasks.*.sort_order' => ['sometimes', 'integer', 'min:0'],
      'task_groups.*.tasks.*.estimated_hours' => ['nullable', 'integer', 'min:1', 'max:1000'],
      'task_groups.*.tasks.*.due_date' => ['nullable', 'date'],

      // Labels on tasks (nested level 3)
      'task_groups.*.tasks.*.labels' => ['sometimes', 'array', 'max:10'],
      'task_groups.*.tasks.*.labels.*.name' => ['required', 'string', 'max:50'],
      'task_groups.*.tasks.*.labels.*.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

      // Subtasks (nested level 3 — recursive)
      'task_groups.*.tasks.*.subtasks' => ['sometimes', 'array', 'max:20'],
      'task_groups.*.tasks.*.subtasks.*.title' => ['required', 'string', 'max:255'],
      'task_groups.*.tasks.*.subtasks.*.description' => ['nullable', 'string', 'max:5000'],
      'task_groups.*.tasks.*.subtasks.*.status' => ['sometimes', 'string', 'in:pending,in_progress,review,completed,cancelled'],
      'task_groups.*.tasks.*.subtasks.*.priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
      'task_groups.*.tasks.*.subtasks.*.assigned_to' => ['nullable', 'integer', 'exists:users,id'],
      'task_groups.*.tasks.*.subtasks.*.estimated_hours' => ['nullable', 'integer', 'min:1', 'max:1000'],
      'task_groups.*.tasks.*.subtasks.*.due_date' => ['nullable', 'date'],

      // Labels on subtasks (nested level 4)
      'task_groups.*.tasks.*.subtasks.*.labels' => ['sometimes', 'array', 'max:10'],
      'task_groups.*.tasks.*.subtasks.*.labels.*.name' => ['required', 'string', 'max:50'],
      'task_groups.*.tasks.*.subtasks.*.labels.*.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
    ];
  }

  /**
   * Custom error messages for more user-friendly validation feedback.
   *
   * @return array<string, string>
   */
  public function messages(): array
  {
    return [
      'name.required' => 'A project name is required.',
      'task_groups.*.name.required' => 'Each task group must have a name.',
      'task_groups.*.tasks.*.title.required' => 'Each task must have a title.',
      'task_groups.*.tasks.*.subtasks.*.title.required' => 'Each subtask must have a title.',
      'task_groups.*.tasks.*.assigned_to.exists' => 'The assigned user does not exist.',
      'task_groups.*.color.regex' => 'Task group color must be a valid hex color (e.g., #FF5733).',
      'due_date.after' => 'The project due date must be in the future.',
      'task_groups.max' => 'A project cannot have more than 20 task groups.',
      'task_groups.*.tasks.max' => 'A task group cannot have more than 50 tasks.',
    ];
  }

  /**
   * Return validation errors as structured JSON instead of redirect.
   */
  protected function failedValidation(Validator $validator): void
  {
    throw new HttpResponseException(
      response()->json([
        'success' => false,
        'message' => 'Validation failed.',
        'errors' => $validator->errors()->toArray(),
      ], 422)
    );
  }
}
