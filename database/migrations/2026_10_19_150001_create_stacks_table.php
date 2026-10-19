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
        Schema::create('stacks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('position')->index();
            $table->string('category');
            $table->string('name');
            $table->text('summary');
            $table->json('headline');
            $table->json('when_to_use');
            $table->json('when_to_avoid');
            $table->json('pros');
            $table->json('cons');
            $table->json('also_learn');
            $table->json('sources');
            $table->date('reviewed_on');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stacks');
    }
};
