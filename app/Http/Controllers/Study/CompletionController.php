<?php

namespace App\Http\Controllers\Study;

use App\Actions\Study\ToggleCompletion;
use App\Enums\CompletableType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Study\ToggleCompletionRequest;
use Illuminate\Http\RedirectResponse;

class CompletionController extends Controller
{
    /**
     * Tick or untick a concept, challenge or interview question.
     * The `{type}` segment is limited to those types by the route.
     */
    public function __invoke(ToggleCompletionRequest $request, string $type, int $id, ToggleCompletion $action): RedirectResponse
    {
        $completable = CompletableType::from($type)->model()::findOrFail($id);

        $action->handle($request->user(), $completable, $request->boolean('completed'));

        return back();
    }
}
