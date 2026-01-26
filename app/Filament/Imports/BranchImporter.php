<?php

namespace App\Filament\Imports;

use App\Models\Branch;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class BranchImporter extends Importer
{
    protected static ?string $model = Branch::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('branch_code')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('branch_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('address'),
            ImportColumn::make('description'),
            ImportColumn::make('sk_branch')
                ->rules(['max:255']),
            ImportColumn::make('sandi_pelapor_kantor_lbu')
                ->rules(['max:255']),
            ImportColumn::make('nama_sandi_pelapor')
                ->rules(['max:255']),
            ImportColumn::make('company_book')
                ->rules(['max:255']),
            ImportColumn::make('company_name')
                ->rules(['max:255']),
            ImportColumn::make('name_address'),
            ImportColumn::make('date_from')
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
            ImportColumn::make('date_to')
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
            ImportColumn::make('version')
                ->rules(['max:255']),
            ImportColumn::make('wib')
                ->rules(['max:255']),
            ImportColumn::make('update_date')
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
            ImportColumn::make('update_regional1')
                ->rules(['max:255']),
            ImportColumn::make('update_date1')
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
            ImportColumn::make('new_regional_head')
                ->rules(['max:255']),
        ];
    }

    public function resolveRecord(): Branch
    {
        return Branch::firstOrNew([
            'branch_code' => $this->data['branch_code'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your branch import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
