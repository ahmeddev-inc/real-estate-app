<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    
    protected function getStats(): array
    {
        return [
            Stat::make('إجمالي العقارات', '245')
                ->description('زيادة 12% عن الشهر الماضي')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),
            
            Stat::make('العقارات المباعة', '128')
                ->description('مبيعات هذا الشهر')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary')
                ->chart([15, 4, 10, 2, 12, 4, 12]),
            
            Stat::make('العملاء الجدد', '42')
                ->description('هذا الشهر')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning')
                ->chart([3, 5, 8, 4, 7, 9, 6]),
            
            Stat::make('إجمالي المبيعات', '8.2 مليون ج.م')
                ->description('قيمة الصفقات هذا الشهر')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([20, 40, 30, 50, 45, 60, 70]),
        ];
    }
    
    protected function getColumns(): int
    {
        return 4;
    }
}
