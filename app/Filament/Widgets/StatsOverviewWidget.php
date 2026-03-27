<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Event;
use App\Models\LotteryTicket;
use App\Models\Winner;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';

    public ?array $filters = [];

    protected $listeners = ['updateFilters' => 'handleFilterUpdate'];

    public function handleFilterUpdate($filters)
    {
        $this->filters = $filters;
    }

    protected function getStats(): array
    {
        $branchIds = $this->filters['summary_branch_ids'] ?? [];
        $startDate = $this->filters['summary_start_date'] ?? null;
        $endDate = $this->filters['summary_end_date'] ?? null;

        $activeEvent = Event::where('status', Event::STATUS_ACTIVE)->first();

        return [
            Stat::make('Total Customers', Customer::query()
                ->when($branchIds, fn($q) => $q->whereIn('branch_id', $branchIds))
                ->count())
                ->description('Total registered CIF')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Active Event', $activeEvent?->event_name ?? 'No Active Event')
                ->description($activeEvent ? 'Currently running' : 'Please activate an event')
                ->descriptionIcon('heroicon-m-calendar')
                ->color($activeEvent ? 'success' : 'danger'),

            Stat::make('Total active Tickets', $activeEvent ? number_format(LotteryTicket::query()
                ->where('event_id', $activeEvent->id)
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
                ->sum('total_points')) : 0)
                ->description('Total issued coupons')
                ->descriptionIcon('heroicon-m-ticket')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('warning'),

            Stat::make('Total Winners', $activeEvent ? Winner::query()
                ->whereHas('eventPrize', fn($q) => $q->where('event_id', $activeEvent->id))
                ->when($branchIds, fn($q) => $q->whereIn('branch_id', $branchIds))
                ->when($startDate, fn($q, $date) => $q->where('drawn_at', '>=', $date))
                ->when($endDate, fn($q, $date) => $q->where('drawn_at', '<=', $date))
                ->count() : 0)
                ->description('Winners for active event')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),

            Stat::make('Failed Uploads', \App\Models\FailedUpload::where('status', 'failed')->count())
                ->description('Pending file uploads')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color(fn() => \App\Models\FailedUpload::where('status', 'failed')->exists() ? 'danger' : 'success')
                ->url(\Modules\LogManagement\Filament\Resources\FailedUploadResource::getUrl()),
        ];
    }
}
