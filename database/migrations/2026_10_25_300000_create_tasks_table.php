<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Times are stored in UTC and shown in each user's timezone.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->string('type')->default('call');
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->unsignedInteger('remind_minutes_before')->nullable();
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_automatic')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'assignee_id', 'completed_at', 'due_at']);
            $table->index(['remind_at', 'reminded_at', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
