<?php

namespace App\Filament\Widgets;

use App\Models\Winner;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class WinnerPrizeDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Winners by Prize Tier';
    protected static ?int $sort = 4;
    protected ?string $maxHeight = '300px';

    public ?array $filters = [];

    protected $listeners = ['updateFilters' => 'handleFilterUpdate'];

    public function handleFilterUpdate($filters)
    {
        $this->filters = $filters;
    }

    protected function getData(): array
    {
        $branchIds = $this->filters['summary_branch_ids'] ?? [];
        $startDate = $this->filters['summary_start_date'] ?? null;
        $endDate = $this->filters['summary_end_date'] ?? null;

        $data = Winner::query()
            ->select('prize_tier', DB::raw('count(*) as total'))
            ->when($branchIds, fn($q, $ids) => $q->whereIn('branch_id', $ids))
            ->when($startDate, fn($q, $date) => $q->where('drawn_at', '>=', $date))
            ->when($endDate, fn($q, $date) => $q->where('drawn_at', '<=', $date))
            ->groupBy('prize_tier')
            ->pluck('total', 'prize_tier')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Winners',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#fbbf24', // Amber
                        '#f59e0b', // Amber dark
                        '#d97706', // Amber darker
                        '#b45309', // Amber darkest
                        '#10b981', // Emerald
                        '#3b82f6', // Blue
                        '#8b5cf6', // Violet
                    ],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
