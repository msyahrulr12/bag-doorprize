<?php

namespace App\Filament\Exports;

use App\Models\Customer;
use App\Models\PointHistory;
use App\Models\LotteryTicket;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PointHistoryFullExporter extends Exporter
{
    protected static ?string $model = PointHistory::class;

    public static function modifyQuery(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->when(!auth()->user()->hasRole('super_admin'), function ($q) {
            $q->whereHas('account', fn($q2) => $q2->whereIn('branch_id', auth()->user()->branches->pluck('id')));
        });
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('account.branch.branch_name')
                ->label('Branch Name'),
            ExportColumn::make('account.customer.cif')
                ->label('CIF'),
            ExportColumn::make('account.customer.name')
                ->label('Customer Name'),
            ExportColumn::make('account.customer.email')
                ->label('Email'),
            ExportColumn::make('account.account_number')
                ->label('Account Number'),
            ExportColumn::make('points')
                ->label('Points (History)'),
            ExportColumn::make('lottery_ticket_total_points')
                ->label('Total Points (Ticket)')
                ->getStateUsing(function (Customer $record) {
                    $ticket = LotteryTicket::where('month', $record->month)
                        ->where('year', $record->year)
                        ->whereHas('participant', fn($q) => $q->where('account_id', $record->account_id))
                        ->first();

                    return $ticket?->total_points;
                }),
            ExportColumn::make('year')
                ->label('Year'),
            ExportColumn::make('month')
                ->label('Month'),
            ExportColumn::make('ticket_start')
                ->label('Ticket Range Start')
                ->getStateUsing(function (PointHistory $record) {
                    $ticket = LotteryTicket::where('month', $record->month)
                        ->where('year', $record->year)
                        ->whereHas('participant', fn($q) => $q->where('account_id', $record->account_id))
                        ->first();

                    return $ticket?->range_start;
                }),
            ExportColumn::make('ticket_end')
                ->label('Ticket Range End')
                ->getStateUsing(function (PointHistory $record) {
                    $ticket = LotteryTicket::where('month', $record->month)
                        ->where('year', $record->year)
                        ->whereHas('participant', fn($q) => $q->where('account_id', $record->account_id))
                        ->first();

                    return $ticket?->range_end;
                }),
            ExportColumn::make('description')
                ->label('Description'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your full point history export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
