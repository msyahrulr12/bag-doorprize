<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Setting;
use Illuminate\Support\Facades\Config;

class SetFilamentTimeoutMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Setting::whereIn('key', [
            'filament_idle_timeout',
            'filament_idle_warning_timeout'
        ])->pluck('value', 'key')->toArray();

        if (isset($settings['filament_idle_timeout'])) {
            Config::set('filament-inactivity-guard.inactivity_timeout', (int) $settings['filament_idle_timeout']);
        }

        if (isset($settings['filament_idle_warning_timeout'])) {
            Config::set('filament-inactivity-guard.notice_timeout', (int) $settings['filament_idle_warning_timeout']);
        }

        return $next($request);
    }
}
