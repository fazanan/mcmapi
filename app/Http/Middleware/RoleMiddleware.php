<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware(['auth','role:admin']) or 'role:member'
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->guest('/login');
        }

        $userRole = strtolower((string)($user->role ?? ''));
        $needRole = strtolower($role);
        if ($userRole !== $needRole) {
            // If not authorized, redirect to Produk for members, or home for admins
            return redirect('/produk');
        }

        return $next($request);
    }
}
