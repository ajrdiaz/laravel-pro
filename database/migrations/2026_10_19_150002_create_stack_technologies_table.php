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
        Schema::create('stack_technologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stack_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('layer');
            $table->string('name');
            $table->text('note');
            $table->timestamps();

            $table->unique(['stack_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stack_technologies');
    }
};
