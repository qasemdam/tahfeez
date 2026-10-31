<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only teacher directory for the group-assignment UI.
 *
 * Manager-only (`users.view`); a teacher has no reason to enumerate colleagues.
 */
class TeacherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor->hasPermission('users.view')) {
            abort(403);
        }

        $teachers = User::query()
            ->where('center_id', $actor->center_id)
            ->where('role', User::ROLE_TEACHER)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active']);

        return response()->json(['data' => $teachers]);
    }
}
