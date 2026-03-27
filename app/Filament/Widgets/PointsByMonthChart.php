<?php

namespace App\Filament\Widgets;

use App\Models\LotteryTicket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PointsByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Points Issued by Month';
    protected static ?int $sort = 3;

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

        $year = now()->year;

        $data = LotteryTicket::query()
            ->select('month', DB::raw('SUM(total_points) as total'))
            ->where('year', $year)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->when($branchIds, fn($q) => $q->whereHas('participant.account', fn($sq) => $sq->whereIn('branch_id', $branchIds)))
            ->when($startDate, function ($q, $date) {
                $start = \Carbon\Carbon::parse($date);
                return $q->where(fn($sq) => $sq->where('year', '>', $start->year)->orWhere(fn($ssq) => $ssq->where('year', $start->year)->where('month', '>=', $start->month)));
            })
            ->when($endDate, function ($q, $date) {
                $end = \Carbon\Carbon::parse($date);
                return $q->where(fn($sq) => $sq->where('year', '<', $end->year)->orWhere(fn($ssq) => $ssq->where('year', $end->year)->where('month', '<=', $end->month)));
            })
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
