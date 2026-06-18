<?php

namespace App\Filament\Exports;

use App\Models\Participant;
use App\Models\LotteryTicket;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class EligibleTicketExporter extends Exporter
{
    protected static ?string $model = Participant::class;

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
            return $query->select(['participants.id'])
                ->setEagerLoads([])
                ->reorder('participants.id', 'desc');
        }

        return $query
            ->with(['account.customer', 'account.branch', 'lotteryTickets'])
            ->withSum([
                'lotteryTickets as active_points' => function ($query) {
                    $query->where('status', LotteryTicket::STATUS_ACTIVE);
                }
            ], 'total_points');
    }

    public static function getColumns(): array
    {
        $indonesianMonths = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        $columns = [
            ExportColumn::make('created_at')
                ->label('Tanggal Data')
                ->state(fn($record) => $record->created_at?->format('d-m-Y')),

            ExportColumn::make('name')
                ->label('Nama')
                ->state(fn($record) => $record->account?->customer?->name),

            ExportColumn::make('cif')
                ->label('No CIF')
                ->state(fn($record) => $record->account?->customer?->cif),

            ExportColumn::make('account_number')
                ->label('No Account')
                ->state(fn($record) => $record->account?->account_number),

            ExportColumn::make('no_ktp')
                ->label('No KTP')
                ->state(fn() => 'N/A'),

            ExportColumn::make('npwp')
                ->label('NPWP')
                ->state(fn() => 'N/A'),

            ExportColumn::make('branch_name')
                ->label('Cabang Pembuka Rekening')
                ->state(fn($record) => $record->account?->branch
                    ? "{$record->account->branch->company_book} - {$record->account->branch->branch_name}"
                    : ''),
        ];

        // Retrieve dynamic active months for lottery tickets
        $activeMonths = [];
        try {
            $activeMonths = LotteryTicket::query()
                ->where('status', LotteryTicket::STATUS_ACTIVE)
                ->select(['month', 'year'])
                ->distinct()
                ->orderBy('year')
                ->orderBy('month')
                ->get();
        } catch (\Exception $e) {
            // fallback
        }

        foreach ($activeMonths as $am) {
            $monthName = $indonesianMonths[$am->month] ?? Carbon::create()->month($am->month)->format('M');
            $columnName = "points_m{$am->month}_y{$am->year}";

            $columns[] = ExportColumn::make($columnName)
                ->label("Poin {$monthName}")
                ->state(function ($record) use ($am) {
                    $ticket = $record->lotteryTickets
                        ->where('status', LotteryTicket::STATUS_ACTIVE)
                        ->where('month', $am->month)
                        ->where('year', $am->year)
                        ->first();
                    return $ticket ? $ticket->total_points : 0;
                });
        }

        $columns[] = ExportColumn::make('active_points')
            ->label('Total Poin')
            ->state(fn($record) => (int) ($record->active_points ?? 0));

        $columns[] = ExportColumn::make('coupon_numbers')
            ->label('No Kupon')
            ->state(function ($record) {
                $tickets = $record->lotteryTickets->where('status', LotteryTicket::STATUS_ACTIVE);
                $ranges = [];
                foreach ($tickets as $ticket) {
                    if (empty($ticket->range_start) || empty($ticket->range_end)) {
                        continue;
                    }
                    $ranges[] = $ticket->range_start === $ticket->range_end
                        ? $ticket->range_start
                        : "{$ticket->range_start} - {$ticket->range_end}";
                }
                return implode(', ', $ranges);
            });

        return $columns;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your eligible ticket export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
