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
            if (! Schema::hasColumn('projects', 'client')) {
                $table->string('client')->nullable()->after('name');
            }

            if (! Schema::hasColumn('projects', 'start_date')) {
                $table->date('start_date')->nullable()->after('client');
            }

            if (! Schema::hasColumn('projects', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }

            if (! Schema::hasColumn('projects', 'status')) {
                $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending')->after('deadline');
            }
        });

        if (Schema::hasColumn('projects', 'start_date')) {
            DB::table('projects')
                ->whereNull('start_date')
                ->update(['start_date' => now()->toDateString()]);
        }

        if (Schema::hasColumn('projects', 'end_date') && Schema::hasColumn('projects', 'deadline')) {
            DB::table('projects')
                ->whereNull('end_date')
                ->whereNotNull('deadline')
                ->update(['end_date' => DB::raw('deadline')]);

            DB::table('projects')
                ->whereNull('end_date')
                ->whereNotNull('start_date')
                ->update(['end_date' => DB::raw('start_date')]);

            DB::table('projects')
                ->whereNull('end_date')
                ->update(['end_date' => now()->toDateString()]);

            DB::table('projects')
                ->whereNull('deadline')
                ->whereNotNull('end_date')
                ->update(['deadline' => DB::raw('end_date')]);
        }

        if (Schema::hasColumn('projects', 'status')) {
            DB::statement("UPDATE projects SET status = 'pending' WHERE status IS NULL OR status = ''");
        }

        if (Schema::hasColumn('projects', 'client')) {
            DB::statement("UPDATE projects SET client = COALESCE(NULLIF(client, ''), name, 'Unknown Client')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty to avoid destructive schema rollback on production data.
    }
};
