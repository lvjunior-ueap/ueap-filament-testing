<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CpaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        styleFilamentPanel($panel);

        return $panel
            ->id('cpa')
            ->path('cpa')
            ->brandName('CPA - Questionários')
            ->discoverResources(in: app_path('Filament/Cpa/Resources'), for: 'App\\Filament\\Cpa\\Resources')
            ->discoverPages(in: app_path('Filament/Cpa/Pages'), for: 'App\\Filament\\Cpa\\Pages')
            ->discoverWidgets(in: app_path('Filament/Cpa/Widgets'), for: 'App\\Filament\\Cpa\\Widgets')
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
