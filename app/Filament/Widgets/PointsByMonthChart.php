<?php

namespace App\Filament\Widgets;

use App\Models\LotteryTicket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PointsByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Points Issued by Month';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $year = now()->year;

        $data = LotteryTicket::query()
            ->select('month', DB::raw('SUM(total_points) as total'))
            ->where('year', $year)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Fill in missing months with 0
        $monthData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthData[] = $data[$i] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Points Issued',
                    'data' => $monthData,
                    'backgroundColor' => '#fbbf24',
                    'borderColor' => '#fbbf24',
                ],
            ],
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
