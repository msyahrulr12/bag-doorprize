<?php

namespace App\Filament\Exports;

use App\Models\PointHistory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class TicketVerificationExporter extends Exporter
{
    protected static ?string $model = PointHistory::class;

    public static function modifyQuery(Builder $query): Builder
    {
        \Illuminate\Support\Facades\DB::connection()->disableQueryLog();

        // Detect if this query is executed during Filament's PrepareCsvExport job
        // (which only collects IDs and doesn't need expensive subqueries/relationships).
        $isPreparing = false;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15) as $step) {
            if (isset($step['class']) && $step['class'] === \Filament\Actions\Exports\Jobs\PrepareCsvExport::class) {
                $isPreparing = true;
                break;
            }
        }

        if ($isPreparing) {
            return $query->select(['point_histories.id'])
                ->setEagerLoads([])
                ->reorder('point_histories.id', 'desc');
        }

        $prevAmountSubquery = PointHistory::query()
            ->from('point_histories as prev_ph')
            ->select('amount')
            ->whereColumn('prev_ph.account_id', 'point_histories.account_id')
            ->whereRaw('prev_ph.month = (CASE WHEN point_histories.month = 1 THEN 12 ELSE point_histories.month - 1 END)')
            ->whereRaw('prev_ph.year = (CASE WHEN point_histories.month = 1 THEN point_histories.year - 1 ELSE point_histories.year END)')
            ->whereNull('prev_ph.deleted_at')
            ->limit(1);

        $prevPointsSubquery = PointHistory::query()
            ->from('point_histories as prev_ph')
            ->selectRaw('COALESCE(SUM(prev_ph.points), 0)')
            ->whereColumn('prev_ph.account_id', 'point_histories.account_id')
            ->whereRaw('(prev_ph.year < point_histories.year OR (prev_ph.year = point_histories.year AND prev_ph.month < point_histories.month))')
            ->whereNull('prev_ph.deleted_at');

        $totalPointsSubquery = PointHistory::query()
            ->from('point_histories as prev_ph')
            ->selectRaw('COALESCE(SUM(prev_ph.points), 0)')
            ->whereColumn('prev_ph.account_id', 'point_histories.account_id')
            ->whereRaw('(prev_ph.year < point_histories.year OR (prev_ph.year = point_histories.year AND prev_ph.month <= point_histories.month))')
            ->whereNull('prev_ph.deleted_at');

        return $query
            ->with(['account.customer', 'account.branch'])
            ->select([
                'point_histories.*',
            ])
            ->selectSub($prevAmountSubquery, 'prev_amount')
            ->selectSub($prevPointsSubquery, 'prev_points')
            ->selectSub($totalPointsSubquery, 'total_points_sum');
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('period')
                ->label('Tanggal Data')
                ->state(fn ($record) => sprintf('%02d-%04d', $record->month, $record->year)),

            ExportColumn::make('name')
                ->label('Nama')
                ->state(fn ($record) => $record->account?->customer?->name),

            ExportColumn::make('cif')
                ->label('No CIF')
                ->state(fn ($record) => $record->account?->customer?->cif),

            ExportColumn::make('account_number')
                ->label('No Account')
                ->state(fn ($record) => $record->account?->account_number),

            ExportColumn::make('branch_name')
                ->label('Cabang Pembuka Rekening')
                ->state(fn ($record) => $record->account?->branch 
                    ? "{$record->account->branch->company_book} - {$record->account->branch->branch_name}" 
                    : ''),

            ExportColumn::make('account_opening_date')
                ->label('Tanggal Buka')
                ->state(fn ($record) => $record->account?->account_opening_date),

            ExportColumn::make('account_opening_balance')
                ->label('Saldo Tanggal Buka')
                ->state(fn ($record) => $record->account?->account_opening_balance),

            ExportColumn::make('prev_amount')
                ->label('Average Balance Bulan Lalu'),

            ExportColumn::make('amount')
                ->label('Average Bulan Berjalan'),

            ExportColumn::make('growth_amount')
                ->label('Growth Bulan Berjalan')
                ->state(fn ($record) => ($record->amount ?? 0) - ($record->prev_amount ?? 0)),

            ExportColumn::make('prev_points')
                ->label('Akumulasi Kupon Bulan Lalu'),

            ExportColumn::make('points')
                ->label('Kupon Bulan Berjalan'),

            ExportColumn::make('total_points_sum')
                ->label('Total Kupon'),

            ExportColumn::make('flag_cif')
                ->label('Flag CIF (NTB / ETB)')
                ->state(function ($record) {
                    if (!$record->account?->account_opening_date) return 'ETB';
                    $openDate = Carbon::parse($record->account->account_opening_date);
                    return ($openDate->year == $record->year && $openDate->month == $record->month) ? 'NTB' : 'ETB';
                }),

            ExportColumn::make('flag_exclude')
                ->label('Flag Exclude')
                ->state(fn ($record) => $record->account?->status === 'EXCLUDE' ? 'Y' : 'N'),

            ExportColumn::make('flag_inactive')
                ->label('Flag Inactive')
                ->state(fn ($record) => $record->account?->status === 'INACTIVE' ? 'Y' : 'N'),

            ExportColumn::make('flag_confi')
                ->label('Flag Confi')
                ->state(fn ($record) => $record->account?->status === 'CONFI' ? 'Y' : 'N'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your ticket verification export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
