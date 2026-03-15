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
        Schema::create('sla_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_response_time');
            $table->integer('max_resolution_time');
            $table->string('priority');
            $table->foreignId('task_id')->unique()->constrained('tasks')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_tasks');
    }
};
