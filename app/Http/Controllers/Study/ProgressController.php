<?php

namespace App\Http\Controllers\Study;

use App\Actions\Study\ResetProgress;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgressController extends Controller
{
    /**
     * Erase the user's study progress.
     */
    public function destroy(Request $request, ResetProgress $action): RedirectResponse
    {
        $action->handle($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Progreso borrado.']);

        return to_route('dashboard');
    }
}
