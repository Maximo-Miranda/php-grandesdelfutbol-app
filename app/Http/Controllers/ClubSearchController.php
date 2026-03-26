<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Club::query()
            ->forUser($request->user())
            ->select(['id', 'ulid', 'name']);

        // Fetch specific clubs by IDs (for recent clubs section)
        if ($ids = $request->string('ids')->value()) {
            $idList = array_map('intval', explode(',', $ids));

            return response()->json([
                'data' => $query->whereIn('id', $idList)->get(),
            ]);
        }

        return response()->json(
            $query->orderBy('name')->simplePaginate(3),
        );
    }
}
