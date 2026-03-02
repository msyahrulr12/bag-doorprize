<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user && $user->must_change_password) {
            $path = Filament::getCurrentPanel()->getPath();
            $targetPath = "/{$path}/change-password";

            // Allow access to the change password page itself and to logout
            if ($request->is("{$path}/change-password") || $request->is("{$path}/logout") || $request->routeIs('filament.admin.pages.change-password')) {
                return $next($request);
            }

            return redirect()->to($targetPath);
        }

        return $next($request);
    }
}
