<?php

namespace App\Http\Controllers\Study;

use App\Actions\Study\SaveNote;
use App\Http\Controllers\Controller;
use App\Http\Requests\Study\UpdateNoteRequest;
use App\Models\Module;
use Illuminate\Http\JsonResponse;

class NoteController extends Controller
{
    /**
     * Save the user's note on a module. The page calls this as the user types, without
     * leaving the page, so the answer is JSON rather than a redirect.
     */
    public function update(UpdateNoteRequest $request, Module $module, SaveNote $action): JsonResponse
    {
        $action->handle($request->user(), $module, $request->validated('body'));

        return response()->json(['saved' => true]);
    }
}
