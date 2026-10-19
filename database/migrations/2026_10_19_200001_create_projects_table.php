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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('position')->index();
            $table->string('level');
            $table->string('title');
            $table->text('summary');
            $table->unsignedSmallInteger('hours');
            // The stack the project is built on, if the catalogue has it.
            $table->foreignId('stack_id')->nullable()->constrained()->nullOnDelete();
            $table->json('deliverables');
            $table->json('stretch');
            $table->timestamps();
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
