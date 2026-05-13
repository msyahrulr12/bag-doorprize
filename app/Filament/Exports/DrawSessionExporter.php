<?php

namespace App\Filament\Exports;

use App\Models\DrawSession;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class DrawSessionExporter extends Exporter
{
    protected static ?string $model = DrawSession::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('event_id'),
            ExportColumn::make('name'),
            ExportColumn::make('started_at'),
            ExportColumn::make('ended_at'),
            ExportColumn::make('winners_count')
                ->label('Total Winners')
                ->state(fn (DrawSession $record): int => ($record->winners_count ?? 0) + ($record->temporary_winners_count ?? 0)),
            ExportColumn::make('total_lottery_generated'),
            ExportColumn::make('status'),
            ExportColumn::make('description'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('deleted_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your draw session export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
