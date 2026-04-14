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
        if (! Schema::hasTable('developers')) {
            return;
        }

        Schema::table('developers', function (Blueprint $table): void {
            if (Schema::hasColumn('developers', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable()->change();
            }
        });

        Schema::table('developers', function (Blueprint $table): void {
            if (Schema::hasColumn('developers', 'password')) {
                $table->dropColumn('password');
            }

            if (Schema::hasColumn('developers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('developers')) {
            return;
        }

        Schema::table('developers', function (Blueprint $table): void {
            if (! Schema::hasColumn('developers', 'password')) {
                $table->string('password')->nullable(false);
            }

            if (! Schema::hasColumn('developers', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active');
            }
        });

        Schema::table('developers', function (Blueprint $table): void {
            if (Schema::hasColumn('developers', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable(false)->change();
            }
        });
    }
};
