<?php

use App\Enums\CompletableType;
use App\Http\Controllers\Study\ChallengeController;
use App\Http\Controllers\Study\CompletionController;
use App\Http\Controllers\Study\FlashcardController;
use App\Http\Controllers\Study\InterviewController;
use App\Http\Controllers\Study\JobAnalysisController;
use App\Http\Controllers\Study\LearnController;
use App\Http\Controllers\Study\NoteController;
use App\Http\Controllers\Study\ProgressController;
use App\Http\Controllers\Study\ProjectController;
use App\Http\Controllers\Study\QuizController;
use App\Http\Controllers\Study\RoadmapController;
use App\Http\Controllers\Study\StackCompareController;
use App\Http\Controllers\Study\StackController;
use App\Http\Controllers\Study\StackWizardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', RoadmapController::class)->name('dashboard');

    Route::put('completions/{type}/{id}', CompletionController::class)
        ->whereIn('type', CompletableType::manual())
        ->whereNumber('id')
        ->name('completions.update');

    Route::get('learn', [LearnController::class, 'index'])->name('learn.index');
    Route::get('learn/{module:slug}', [LearnController::class, 'module'])->name('learn.module');
    Route::get('learn/{module:slug}/{lesson:slug}', [LearnController::class, 'lesson'])->name('learn.lesson');
    Route::put('learn/{module:slug}/notes', [NoteController::class, 'update'])->name('notes.update');

    Route::get('flashcards', [FlashcardController::class, 'index'])->name('flashcards.index');
    Route::get('flashcards/export', [FlashcardController::class, 'export'])->name('flashcards.export');
    Route::post('flashcards/{flashcard}/review', [FlashcardController::class, 'review'])->name('flashcards.review');

    Route::get('quiz', [QuizController::class, 'index'])->name('quiz.index');
    Route::post('quiz/{question}/answer', [QuizController::class, 'answer'])->name('quiz.answer');

    Route::get('challenges', ChallengeController::class)->name('challenges.index');
    Route::get('interview', InterviewController::class)->name('interview.index');

    Route::get('stacks', [StackController::class, 'index'])->name('stacks.index');
    // These two must come before `{stack:slug}`, which would otherwise take them as a slug.
    Route::get('stacks/wizard', StackWizardController::class)->name('stacks.wizard');
    Route::get('stacks/compare', StackCompareController::class)->name('stacks.compare');
    Route::get('stacks/{stack:slug}', [StackController::class, 'show'])->name('stacks.show');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('jobs', [JobAnalysisController::class, 'index'])->name('jobs.index');
    Route::post('jobs', [JobAnalysisController::class, 'store'])->name('jobs.store');
    Route::get('jobs/{analysis}', [JobAnalysisController::class, 'show'])->name('jobs.show');
    Route::delete('jobs/{analysis}', [JobAnalysisController::class, 'destroy'])->name('jobs.destroy');

    Route::delete('progress', [ProgressController::class, 'destroy'])->name('progress.destroy');
});

require __DIR__.'/settings.php';
