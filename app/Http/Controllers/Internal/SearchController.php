<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\Search\GlobalSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): View|JsonResponse
    {
        $term = mb_substr(trim((string) $request->query('q')), 0, 100);
        $groups = $search->search($request->user(), $term);

        if ($request->expectsJson()) {
            return response()->json(['query' => $term, 'groups' => $groups]);
        }

        return view('internal.search', ['term' => $term, 'groups' => $groups]);
    }
}
