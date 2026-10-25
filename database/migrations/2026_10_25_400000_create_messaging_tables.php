<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('external_id');
            $table->string('business_account_id')->nullable();
            $table->string('name');
            $table->string('display_handle')->nullable();
            $table->text('access_token');
            $table->string('status')->default('connected');
            $table->string('status_message')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['channel', 'external_id']);
            $table->index(['team_id', 'channel']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_thread_id');
            $table->string('display_name')->nullable();
            $table->string('handle')->nullable();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('awaiting_reply_since')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['channel_account_id', 'external_thread_id']);
            $table->index(['team_id', 'last_message_at']);
            $table->index(['team_id', 'assignee_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('external_id')->nullable()->unique();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('media_external_id')->nullable();
            $table->string('media_url', 2048)->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('template_name')->nullable();
            $table->string('status');
            $table->string('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('response_seconds')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['team_id', 'direction', 'created_at']);
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('language');
            $table->string('category')->nullable();
            $table->string('status');
            $table->text('body')->nullable();
            $table->unsignedTinyInteger('parameter_count')->default(0);
            $table->timestamps();

            $table->unique(['channel_account_id', 'name', 'language']);
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->json('payload');
            $table->boolean('signature_valid');
            $table->timestamp('processed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('channel_accounts');
    }
};
