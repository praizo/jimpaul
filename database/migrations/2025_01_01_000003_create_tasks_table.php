<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('tasks', function (Blueprint $table) {
      $table->id();
      $table->foreignId('task_group_id')->constrained()->cascadeOnDelete();
      $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
      $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
      $table->string('title');
      $table->text('description')->nullable();
      $table->enum('status', ['pending', 'in_progress', 'review', 'completed', 'cancelled'])->default('pending');
      $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
      $table->unsignedInteger('sort_order')->default(0);
      $table->unsignedInteger('estimated_hours')->nullable();
      $table->dateTime('due_date')->nullable();
      $table->dateTime('completed_at')->nullable();
      $table->timestamps();
      $table->softDeletes();

      $table->index(['task_group_id', 'sort_order']);
      $table->index(['parent_id']);
      $table->index(['assigned_to', 'status']);
      $table->index(['status', 'due_date']);
      $table->fullText(['title', 'description']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('tasks');
  }
};
