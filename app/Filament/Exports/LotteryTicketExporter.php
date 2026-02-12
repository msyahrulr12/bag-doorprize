<?php

namespace App\Filament\Exports;

use App\Models\LotteryTicket;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class LotteryTicketExporter extends Exporter
{
    protected static ?string $model = LotteryTicket::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('event.event_name')
                ->label('Event'),
            ExportColumn::make('participant.participant_name')
                ->label('Participant'),
            ExportColumn::make('total_points')
                ->label('Points'),
            ExportColumn::make('range_start')
                ->label('Start Range'),
            ExportColumn::make('range_end')
                ->label('End Range'),
            ExportColumn::make('status'),
            ExportColumn::make('month'),
            ExportColumn::make('year'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your lottery ticket export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
