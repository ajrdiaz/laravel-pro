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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->unsignedSmallInteger('position')->index();
            $table->string('title');
            $table->text('summary');
            $table->unsignedSmallInteger('minutes');
            $table->longText('body');
            $table->timestamps();

            // The slug is a lesson's identity, so reordering lessons keeps their ids (and progress).
            $table->unique(['module_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
