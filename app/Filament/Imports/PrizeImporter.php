<?php

namespace App\Filament\Imports;

use App\Models\Prize;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class PrizeImporter extends Importer
{
    protected static ?string $model = Prize::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('prize_code')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('prize_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('prize_image'),
            ImportColumn::make('tier')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('value')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('description'),
        ];
    }

    public function resolveRecord(): Prize
    {
        return Prize::firstOrNew([
            'prize_code' => $this->data['prize_code'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your prize import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
