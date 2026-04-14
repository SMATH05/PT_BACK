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
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            if (! Schema::hasColumn('projects', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('projects', 'chef_de_projet_id')) {
                $table->unsignedBigInteger('chef_de_projet_id')->nullable()->after('manager_id');
            }

            if (! Schema::hasColumn('projects', 'deadline')) {
                $table->date('deadline')->nullable()->after('end_date');
            }

            if (! Schema::hasColumn('projects', 'folder_path')) {
                $table->string('folder_path')->nullable()->after('description');
            }
        });

        if (Schema::hasColumn('projects', 'end_date') && Schema::hasColumn('projects', 'deadline')) {
            DB::table('projects')
                ->whereNull('deadline')
                ->whereNotNull('end_date')
                ->update(['deadline' => DB::raw('end_date')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            if (Schema::hasColumn('projects', 'folder_path')) {
                $table->dropColumn('folder_path');
            }

            if (Schema::hasColumn('projects', 'deadline')) {
                $table->dropColumn('deadline');
            }

            if (Schema::hasColumn('projects', 'chef_de_projet_id')) {
                $table->dropColumn('chef_de_projet_id');
            }

            if (Schema::hasColumn('projects', 'manager_id')) {
                $table->dropColumn('manager_id');
            }
        });
    }
};
