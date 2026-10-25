<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money columns are integer minor units (paisa) in the workspace currency.
     */
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'sort']);
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->string('kind')->default('open');
            $table->timestamps();

            $table->index(['pipeline_id', 'sort']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['team_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('unit_price')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(13);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'sku']);
            $table->index(['team_id', 'is_active']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('handle')->nullable();
            $table->string('company_name')->nullable();
            $table->string('source')->default('manual');
            $table->string('status')->default('new');
            $table->bigInteger('estimated_value')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('converted_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('converted_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->unsignedBigInteger('converted_deal_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('unqualified_reason')->nullable();
            $table->unsignedInteger('board_position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status', 'board_position']);
            $table->index(['team_id', 'owner_id']);
            $table->index(['team_id', 'source']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->char('currency', 3)->default('NPR');
            $table->date('expected_close_on')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('board_position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'stage_id', 'board_position']);
            $table->index(['team_id', 'owner_id']);
            $table->index(['team_id', 'closed_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreign('converted_deal_id')->references('id')->on('deals')->nullOnDelete();
        });

        Schema::create('deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_products');
        Schema::table('leads', fn (Blueprint $table) => $table->dropForeign(['converted_deal_id']));
        Schema::dropIfExists('deals');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('pipelines');
    }
};
