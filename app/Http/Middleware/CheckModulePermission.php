<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckModulePermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (Auth::check() && !Auth::user()->hasPermissionToModule($module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'This feature module has been disabled for your account by the Superadmin.'
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', 'The "' . ucfirst($module) . '" module is currently disabled for your account by the Superadmin.');
        }

        return $next($request);
    }
}
