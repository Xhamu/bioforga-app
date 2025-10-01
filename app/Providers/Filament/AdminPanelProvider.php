<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Register;
use App\Filament\Resources\Auth\Login\Login;
use App\Filament\Widgets\AccountWidget;
use App\Filament\Widgets\EstadoUsuarioWidget;
use App\Filament\Widgets\PartesTrabajoActivos;
use App\Filament\Widgets\ResumenPartesActivos;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Monzer\FilamentChatifyIntegration\ChatifyPlugin;
use Phpsa\FilamentAuthentication\Widgets\LatestUsersWidget;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('default')
            ->path('')
            ->passwordReset()
            ->brandName('Bioforga')
            ->brandLogo(asset('images/bioforga-logo.png'))
            ->brandLogoHeight('3.5rem')
            ->favicon(asset('favicons/favicon.ico'))
            ->login(Login::class)
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Green,
            ])

            ->darkMode(false)

            ->renderHook('panels::page.end', fn() => view('components.gps-global-script'))
            ->renderHook('panels::auth.login.form.after', fn() => view('components.gps-global-script'))
            ->renderHook('panels::body.start', function () {
                if (!auth()->user()?->hasRole('superadmin')) {
                    return <<<HTML
            <style>
                li[data-group-label="Ajustes generales"] {
                    display: none !important;
                }
            </style>
        HTML;
                }

                return null;
            })


            ->renderHook(
                'panels::body.start',
                fn() => '
        <style>
            .fi-main {
                max-width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
        </style>'
            )

            ->plugins([
                \TomatoPHP\FilamentPWA\FilamentPWAPlugin::make(),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 2
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 3,
                    ]),
                //ChatifyPlugin::make(),
            ])

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
                \App\Filament\Pages\MapaReferencias::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                PartesTrabajoActivos::class,
                ResumenPartesActivos::class,
                EstadoUsuarioWidget::class,
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
