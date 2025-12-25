<?php

namespace App\Providers\Filament;

use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->brandName('عقار زين')
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.ico'))
            ->font('Cairo', provider: GoogleFontProvider::class)
            ->colors([
                'primary' => '#3B82F6',
                'secondary' => '#10B981',
                'danger' => '#EF4444',
                'warning' => '#F59E0B',
                'info' => '#3B82F6',
                'success' => '#10B981',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->sidebarCollapsibleOnDesktop()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->plugins([
                \BezhanSalleh\FilamentShield\FilamentShieldPlugin::make(),
            ])
            ->spa()
            ->maxContentWidth('7xl')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                'العقارات',
                'العملاء',
                'المبيعات',
                'المهام',
                'التقارير',
                'الإعدادات',
            ])
            ->navigationItems([
                // سيتم إضافة عناصر التنقل هنا
            ]);
    }
}
