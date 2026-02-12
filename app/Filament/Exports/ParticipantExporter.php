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
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('event.event_name')
                ->label('Event'),
            ExportColumn::make('participant_name')
                ->label('Name'),
            ExportColumn::make('participant_cif')
                ->label('CIF'),
            ExportColumn::make('participant_account_number')
                ->label('Account'),
            ExportColumn::make('participant_email')
                ->label('Email'),
            ExportColumn::make('participant_phone_number')
                ->label('Phone'),
            ExportColumn::make('total_points_snapshot')
                ->label('Points Snapshot'),
            ExportColumn::make('status'),
            ExportColumn::make('created_at'),
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
