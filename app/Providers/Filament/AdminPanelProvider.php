<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\URL;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Nwidart\Modules\Facades\Module;
use EightCedars\FilamentInactivityGuard\FilamentInactivityGuardPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $enabledModules = Module::allEnabled();

        if (env('APP_ENV') !== 'local') {
            URL::forceScheme(env('APP_PROTOCOL', 'https'));
        }

        $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilamentInactivityGuardPlugin::make()
                ->inactiveAfter(config('filament-inactivity-guard.inactivity_timeout'))
                ->showNoticeFor(config('filament-inactivity-guard.notice_timeout'))
            )
            // ->brandName('BAGI UNDIAN')
            ->brandLogo(asset('images/logo-agi.png'))
            ->brandLogoHeight('2.5rem')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ]);

        foreach ($enabledModules as $module) {
            $moduleName = $module->getName();

            $panel->discoverResources(
                in: $module->getPath() . '/app/Filament/Resources',
                for: "Modules\\{$moduleName}\\Filament\\Resources"
            )
                ->discoverPages(
                    in: $module->getPath() . '/app/Filament/Pages',
                    for: "Modules\\{$moduleName}\\Filament\\Pages"
                )
                ->discoverWidgets(
                    in: $module->getPath() . '/app/Filament/Widgets',
                    for: "Modules\\{$moduleName}\\Filament\\Widgets"
                );
        }

        return $panel
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\Filament\Resources'
            )
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Default widgets removed to focus on custom business dashboard
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SetFilamentTimeoutMiddleware::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsurePasswordIsChanged::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s');
    }
}
