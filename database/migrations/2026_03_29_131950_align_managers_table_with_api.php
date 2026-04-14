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
        if (! Schema::hasTable('managers')) {
            return;
        }

        Schema::table('managers', function (Blueprint $table): void {
            if (! Schema::hasColumn('managers', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
        });

        Schema::table('managers', function (Blueprint $table): void {
            if (Schema::hasColumn('managers', 'password')) {
                $table->dropColumn('password');
            }
        });

        Schema::table('managers', function (Blueprint $table): void {
            if (Schema::hasColumn('managers', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('managers')) {
            return;
        }

        Schema::table('managers', function (Blueprint $table): void {
            if (! Schema::hasColumn('managers', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('managers', function (Blueprint $table): void {
            if (! Schema::hasColumn('managers', 'password')) {
                $table->string('password')->nullable(false);
            }
        });
    }
};
