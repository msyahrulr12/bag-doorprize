<?php

namespace App\Filament\Imports;

use App\Models\Event;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class EventImporter extends Importer
{
    protected static ?string $model = Event::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('event_code')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('event_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('event_image'),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('event_started_at')
                ->rules(['required', 'date']),
            ImportColumn::make('event_ended_at')
                ->rules(['required', 'date', 'after:event_started_at']),
            ImportColumn::make('description'),
            ImportColumn::make('last_ticket_number'),
        ];
    }

    public function resolveRecord(): Event
    {
        return Event::firstOrNew([
            'event_code' => $this->data['event_code'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your event import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
