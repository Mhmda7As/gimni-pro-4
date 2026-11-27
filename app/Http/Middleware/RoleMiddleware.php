<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (!$user->is_active) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Account is deactivated.'], 403);
            }
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'تم تعطيل هذا الحساب.']);
        }

        if (!in_array($user->role, $roles, true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthorized role for this action.'], 403);
            }
            abort(403, 'عذراً، لا تملك صلاحية الوصول إلى هذه الصفحة.');
        }

        return $next($request);
    }
}