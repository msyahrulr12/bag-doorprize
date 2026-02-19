<?php

namespace App\Filament\Exports;

use App\Models\Branch;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class BranchExporter extends Exporter
{
    protected static ?string $model = Branch::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('branch_code'),
            ExportColumn::make('branch_name'),
            ExportColumn::make('address'),
            ExportColumn::make('description'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
            ExportColumn::make('deleted_at'),
            ExportColumn::make('sk_branch'),
            ExportColumn::make('sandi_pelapor_kantor_lbu'),
            ExportColumn::make('nama_sandi_pelapor'),
            ExportColumn::make('company_book'),
            ExportColumn::make('company_name'),
            ExportColumn::make('name_address'),
            ExportColumn::make('date_from'),
            ExportColumn::make('date_to'),
            ExportColumn::make('version'),
            ExportColumn::make('wib'),
            ExportColumn::make('update_date'),
            ExportColumn::make('update_regional1'),
            ExportColumn::make('update_date1'),
            ExportColumn::make('new_regional_head'),
            ExportColumn::make('status'),
            ExportColumn::make('region'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your branch export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
