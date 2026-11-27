<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'Account is inactive or unauthenticated.'], 403);
        }

        if (!empty($roles) && !in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Unauthorized role for this action.'], 403);
        }

        return $next($request);
    }
}