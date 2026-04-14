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
        if (Schema::hasTable('projects')) {
            return;
        }

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('deadline')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->constrained('managers')->cascadeOnDelete();
            $table->foreignId('chef_de_projet_id')->constrained('chef_de_projets')->cascadeOnDelete();
            $table->string('folder_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
