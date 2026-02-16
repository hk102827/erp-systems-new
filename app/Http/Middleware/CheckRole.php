<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive.'
            ], 403);
        }

        if (!$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'No role assigned to this user.'
            ], 403);
        }

        // Check if user's role matches any of the required roles
        if (!in_array($user->role->role_name, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have the required role to access this resource.'
            ], 403);
        }

        return $next($request);
    }
}