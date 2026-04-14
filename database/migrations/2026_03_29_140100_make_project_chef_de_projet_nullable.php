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
        if (!Schema::hasTable('projects') || !Schema::hasColumn('projects', 'chef_de_projet_id')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('chef_de_projet_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('projects') || !Schema::hasColumn('projects', 'chef_de_projet_id')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('chef_de_projet_id')->nullable(false)->change();
        });
    }
};
