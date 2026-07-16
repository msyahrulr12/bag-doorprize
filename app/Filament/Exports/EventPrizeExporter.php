<?php

namespace App\Filament\Exports;

use App\Models\EventPrize;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class EventPrizeExporter extends Exporter
{
    protected static ?string $model = EventPrize::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('event.event_name'),
            ExportColumn::make('prize.prize_name'),
            ExportColumn::make('total_quantity'),
            ExportColumn::make('remaining_quantity'),
            ExportColumn::make('min_points_required'),
            ExportColumn::make('max_points_required'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event prize export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
