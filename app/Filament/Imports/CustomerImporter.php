<?php

namespace App\Filament\Imports;

use App\Models\Customer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class CustomerImporter extends Importer
{
    protected static ?string $model = Customer::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('branch_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('cif')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('phone_number')
                ->requiredMapping()
                ->rules(['required', 'max:15']),
            ImportColumn::make('address'),
            ImportColumn::make('description'),
            ImportColumn::make('total_point_sum')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('redeemed_points')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('status')
                ->rules(['max:255'])
                ->castStateUsing(function ($state) {
                    return $state == '1' || strtoupper($state) == 'ACTIVE' ? Customer::STATUS_ACTIVE : Customer::STATUS_INACTIVE;
                }),
            ImportColumn::make('date_of_birth')
                ->rules(['nullable', 'date'])
                ->castStateUsing(function ($state) {
                    if (empty($state))
                        return null;
                    try {
                        return \Carbon\Carbon::parse($state)->format('Y-m-d');
                    } catch (\Exception $e) {
                        return null;
                    }
                }),
        ];
    }

    public function resolveRecord(): Customer
    {
        return Customer::firstOrNew([
            'cif' => $this->data['cif'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your customer import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
