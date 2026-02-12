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

    protected function getStats(): array
    {
        $activeEvent = Event::where('status', Event::STATUS_ACTIVE)->first();

        return [
            Stat::make('Total Customers', Customer::count())
                ->description('Total registered CIF')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Active Event', $activeEvent?->event_name ?? 'No Active Event')
                ->description($activeEvent ? 'Currently running' : 'Please activate an event')
                ->descriptionIcon('heroicon-m-calendar')
                ->color($activeEvent ? 'success' : 'danger'),

            Stat::make('Total active Tickets', $activeEvent ? number_format(LotteryTicket::where('event_id', $activeEvent->id)->where('status', LotteryTicket::STATUS_ACTIVE)->sum('total_points')) : 0)
                ->description('Total issued coupons')
                ->descriptionIcon('heroicon-m-ticket')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('warning'),

            Stat::make('Total Winners', $activeEvent ? Winner::whereHas('eventPrize', fn($q) => $q->where('event_id', $activeEvent->id))->count() : 0)
                ->description('Winners for active event')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),
        ];
    }
}
