<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('task_groups', function (Blueprint $table) {
      $table->id();
      $table->foreignId('project_id')->constrained()->cascadeOnDelete();
      $table->string('name');
      $table->text('description')->nullable();
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('color', 7)->nullable();
      $table->timestamps();

      $table->index(['project_id', 'sort_order']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('task_groups');
  }
};
