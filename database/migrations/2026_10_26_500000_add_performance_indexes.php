<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for list pages at 50,000+ records per workspace: every list filters on team_id and deleted_at,
 * then sorts; contact search looks up normalised emails/phones by prefix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['team_id', 'deleted_at', 'first_name', 'id'], 'contacts_list_index');
            $table->index(['team_id', 'last_name'], 'contacts_last_name_index');
        });

        Schema::table('contact_methods', function (Blueprint $table) {
            $table->index(['team_id', 'normalized_value'], 'contact_methods_lookup_index');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->index(['team_id', 'deleted_at', 'name'], 'companies_list_index');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index(['team_id', 'deleted_at', 'created_at'], 'leads_list_index');
            $table->index(['team_id', 'deleted_at', 'status', 'board_position'], 'leads_board_index');
        });

        Schema::table('deals', function (Blueprint $table) {
            $table->index(['team_id', 'deleted_at', 'created_at'], 'deals_list_index');
            $table->index(['team_id', 'deleted_at', 'stage_id', 'closed_at'], 'deals_board_index');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->index(['subject_type', 'subject_id', 'happened_at'], 'activities_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->dropIndex('activities_timeline_index'));
        Schema::table('deals', function (Blueprint $table) {
            $table->dropIndex('deals_list_index');
            $table->dropIndex('deals_board_index');
        });
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_list_index');
            $table->dropIndex('leads_board_index');
        });
        Schema::table('companies', fn (Blueprint $table) => $table->dropIndex('companies_list_index'));
        Schema::table('contact_methods', fn (Blueprint $table) => $table->dropIndex('contact_methods_lookup_index'));
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_list_index');
            $table->dropIndex('contacts_last_name_index');
        });
    }
};
