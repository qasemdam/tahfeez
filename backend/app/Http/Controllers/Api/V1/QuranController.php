<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Quran\Contracts\QuranDataProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Read-only Quran reference data, served through the provider.
 *
 * The controller knows nothing about where the rows come from: the binding
 * resolves to the active database snapshot in production and to the development
 * fixture outside it (config/quran.php). When no usable snapshot exists the
 * provider raises QuranDataUnavailable, which the API renders as 503.
 */
class QuranController extends Controller
{
    public function surahs(QuranDataProvider $quran): JsonResponse
    {
        return response()->json(['data' => $quran->surahs()]);
    }
}
