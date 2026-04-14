<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('tasks', 'description')) {
                $table->text('description')->nullable()->after('title');
            }

            if (! Schema::hasColumn('tasks', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->after('status');
            }

            if (! Schema::hasColumn('tasks', 'chef_de_projet_id')) {
                $table->unsignedBigInteger('chef_de_projet_id')->nullable()->after('project_id');
            }
        });

        // Keep backward compatibility with old status naming from legacy schema.
        if (Schema::hasColumn('tasks', 'status')) {
            DB::statement("UPDATE tasks SET status = 'done' WHERE status = 'completed'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};
