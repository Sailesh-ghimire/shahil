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
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedInteger('created_rows')->default(0)->after('successful_rows');
            $table->unsignedInteger('updated_rows')->default(0)->after('created_rows');
            $table->unsignedInteger('skipped_rows')->default(0)->after('updated_rows');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn(['created_rows', 'updated_rows', 'skipped_rows']);
        });
    }
};
