<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Read-only Quran reference data, served through the provider.
 *
 * The current binding is a development fixture, not canonical data (OD-029).
 */
class QuranController extends Controller
{
    public function surahs(QuranDataProvider $quran): JsonResponse
    {
        return response()->json(['data' => $quran->surahs()]);
    }
}
