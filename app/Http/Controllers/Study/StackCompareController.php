<?php

namespace App\Http\Controllers\Study;

use App\Http\Controllers\Controller;
use App\Services\Stacks\StackPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class StackCompareController extends Controller
{
    /** The most stacks the table shows side by side. */
    private const int MAX_STACKS = 3;

    /**
     * Compare two or three stacks layer by layer. The choice travels in the query string
     * (`?stacks[]=rest-api&stacks[]=...`), so a comparison can be bookmarked or shared.
     */
    public function __invoke(Request $request, StackPresenter $presenter): Response
    {
        $options = $presenter->options();
        $known = $options->pluck('slug')->all();

        // Unknown and repeated slugs are skipped before counting, so a stray one in the
        // link never costs a stack its place.
        $slugs = [];

        foreach (Arr::wrap($request->query('stacks')) as $slug) {
            if (is_string($slug) && in_array($slug, $known, true) && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }

            if (count($slugs) === self::MAX_STACKS) {
                break;
            }
        }

        $stacks = $presenter->bySlugs($slugs);
        $comparing = $stacks->count() >= 2;

        $columns = [];

        if ($comparing) {
            foreach ($stacks as $stack) {
                $columns[] = $presenter->column($request->user(), $stack);
            }
        }

        return Inertia::render('StackCompare', [
            'options' => $options,
            'selected' => $slugs,
            'columns' => $columns,
            'layers' => $comparing ? $presenter->layersUsedBy($stacks) : [],
        ]);
    }
}
