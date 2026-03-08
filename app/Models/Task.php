<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'task_group_id',
    'parent_id',
    'assigned_to',
    'title',
    'description',
    'status',
    'priority',
    'sort_order',
    'estimated_hours',
    'due_date',
    'completed_at',
  ];

  /**
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'due_date' => 'datetime',
      'completed_at' => 'datetime',
    ];
  }

  public function taskGroup(): BelongsTo
  {
    return $this->belongsTo(TaskGroup::class);
  }

  public function parent(): BelongsTo
  {
    return $this->belongsTo(Task::class, 'parent_id');
  }

  public function subtasks(): HasMany
  {
    return $this->hasMany(Task::class, 'parent_id')->orderBy('sort_order');
  }

  public function assignee(): BelongsTo
  {
    return $this->belongsTo(User::class, 'assigned_to');
  }

  public function labels(): BelongsToMany
  {
    return $this->belongsToMany(Label::class, 'task_labels')->withTimestamps();
  }
}
