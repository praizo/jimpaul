<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
  use HasFactory, SoftDeletes;

  protected $fillable = [
    'user_id',
    'name',
    'description',
    'status',
    'priority',
    'due_date',
    'settings',
  ];

  /**
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'due_date' => 'date',
      'settings' => 'array',
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function taskGroups(): HasMany
  {
    return $this->hasMany(TaskGroup::class)->orderBy('sort_order');
  }

  public function tasks(): HasManyThrough
  {
    return $this->hasManyThrough(Task::class, TaskGroup::class);
  }
}
