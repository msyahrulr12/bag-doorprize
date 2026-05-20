<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplicationLockMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if application is locked via Settings
        $isLocked = Cache::remember('app_locked_status', 30, function () {
            // Failsafe in case table is not ready
            try {
                $setting = Setting::where('key', 'application_locked')->first();
                return $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : false;
            } catch (\Exception $e) {
                return false;
            }
        });

        if (!$isLocked) {
            return $next($request);
        }

        // 2. Allow specific critical routes to bypass the lock
        // Allow public draw pages so the event can still happen
        if ($request->is('draw/*') || $request->is('draw-bulk/*')) {
            return $next($request);
        }

        // Allow livewire updates (Livewire components handle their own auth inside)
        if ($request->is('livewire/update') || $request->is('livewire/*')) {
             return $next($request);
        }

        // Allow login/logout routes for Filament and the Locked page itself
        if ($request->is('admin/login') || $request->is('admin/logout') || $request->is('locked') || $request->routeIs('filament.admin.auth.*') || $request->routeIs('locked')) {
            return $next($request);
        }

        // 3. Allow Super Admin, Admin, or Excluded Users/Roles to bypass
        $user = Auth::user();
        if ($user) {
            if (method_exists($user, 'hasRole') && ($user->hasRole('super_admin') || $user->hasRole('Admin'))) {
                return $next($request);
            }

            // Check excluded emails setting
            $excludedEmails = Cache::remember('app_locked_excluded_emails', 30, function () {
                try {
                    $setting = Setting::where('key', 'application_locked_excluded_emails')->first();
                    return $setting && $setting->value ? explode(',', strtolower(str_replace(' ', '', $setting->value))) : [];
                } catch (\Exception $e) {
                    return [];
                }
            });

            if (in_array(strtolower($user->email), $excludedEmails)) {
                return $next($request);
            }

            // Check excluded roles setting
            if (method_exists($user, 'hasRole')) {
                $excludedRoles = Cache::remember('app_locked_excluded_roles', 30, function () {
                    try {
                        $setting = Setting::where('key', 'application_locked_excluded_roles')->first();
                        return $setting && $setting->value ? array_map('trim', explode(',', $setting->value)) : [];
                    } catch (\Exception $e) {
                        return [];
                    }
                });

                foreach ($excludedRoles as $role) {
                    if ($user->hasRole($role)) {
                        return $next($request);
                    }
                }
            }
            
            // Instead of logging out immediately, we redirect them to the locked page
            // The locked page will handle showing them the 'locked' status and providing a logout button
            return redirect()->route('locked');
        }

        // If not authenticated and trying to access something else, redirect to locked page
        return redirect()->route('locked');
    }
}
