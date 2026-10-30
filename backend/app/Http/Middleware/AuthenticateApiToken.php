<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bearer-token authentication (BR-SEC-03).
 *
 * Tokens are device-scoped and stored hashed; the plaintext never touches the
 * database. Revoking a device is deleting its token row.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! is_string($bearer) || $bearer === '') {
            return $this->unauthorized('Missing bearer token.');
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $bearer))
            ->first();

        if ($token === null || $token->isExpired() || $token->user === null || ! $token->user->is_active) {
            return $this->unauthorized('Invalid or expired token.');
        }

        $request->setUserResolver(static fn () => $token->user);
        $request->attributes->set('api_token', $token);

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    private function unauthorized(string $detail): Response
    {
        return response()->json([
            'type' => 'https://tahfeez.app/errors/unauthenticated',
            'title' => 'Unauthenticated',
            'status' => 401,
            'detail' => $detail,
        ], 401);
    }
}
