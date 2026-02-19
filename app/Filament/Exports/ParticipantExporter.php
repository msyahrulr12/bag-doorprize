<?php

namespace App\Filament\Exports;

use App\Models\Participant;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ParticipantExporter extends Exporter
{
    protected static ?string $model = Participant::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('event.event_name'),
            ExportColumn::make('participant_name'),
            ExportColumn::make('participant_cif'),
            ExportColumn::make('participant_account_number'),
            ExportColumn::make('participant_email'),
            ExportColumn::make('participant_phone_number'),
            ExportColumn::make('total_points_snapshot'),
            ExportColumn::make('status'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('deleted_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your participant export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
