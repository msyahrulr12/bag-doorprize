<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestWinnersWidget;
use App\Filament\Widgets\PointsByMonthChart;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\WinnerPrizeDistributionChart;
use Filament\Pages\Dashboard as BaseDashboard;
use BackedEnum;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Bagi Hoki Dashboard';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            \App\Filament\Widgets\LatestFailedUploadsWidget::class,
            PointsByMonthChart::class,
            WinnerPrizeDistributionChart::class,
            LatestWinnersWidget::class,
        ];
    }
}
