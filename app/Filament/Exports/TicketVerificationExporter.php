<?php

namespace App\Filament\Exports;

use App\Models\Event;
use App\Models\Participant;
use App\Models\PointHistory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

class TicketVerificationExporter extends Exporter
{
    protected static ?string $model = PointHistory::class;

    protected static array $staticOptions = [];

    public function __construct(Export $export, array $columnMap, array $options)
    {
        parent::__construct($export, $columnMap, $options);
        self::$staticOptions = $options;
    }

    public function getCachedColumns(): array
    {
        self::$staticOptions = $this->options;
        return parent::getCachedColumns();
    }

    public function __invoke(\Illuminate\Database\Eloquent\Model $record): array
    {
        self::$staticOptions = $this->options;
        return parent::__invoke($record);
    }

    protected static function getEventId(): ?int
    {
        $event = request()->route('record');
        if ($event) {
            return $event instanceof Event ? $event->id : (is_numeric($event) ? (int) $event : null);
        }

        if (isset(self::$staticOptions['event_id'])) {
            return (int) self::$staticOptions['event_id'];
        }

        return null;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        DB::connection()->disableQueryLog();

        $eventId = self::getEventId();
        $event = $eventId ? Event::find($eventId) : null;

        // Build a fresh, optimized query with JOINs instead of eager loading
        $query = PointHistory::withoutGlobalScopes()
            ->select([
                'point_histories.*',
                'prev_ph.amount AS prev_amount',
                'branches.company_book AS branch_company_book',
                'branches.branch_name AS branch_name_value',
                'accounts.account_number',
                'accounts.status AS account_status',
                'accounts.account_opening_date',
                'accounts.account_opening_balance',
                'customers.name AS customer_name',
                'customers.cif AS customer_cif',
            ])
            ->leftJoin('point_histories AS prev_ph', function ($join) {
                $join->on('prev_ph.account_id', '=', 'point_histories.account_id')
                    ->whereRaw('prev_ph.month = (CASE WHEN point_histories.month = 1 THEN 12 ELSE point_histories.month - 1 END)')
                    ->whereRaw('prev_ph.year = (CASE WHEN point_histories.month = 1 THEN point_histories.year - 1 ELSE point_histories.year END)')
                    ->whereNull('prev_ph.deleted_at');
            })
            ->leftJoin('accounts', 'accounts.id', '=', 'point_histories.account_id')
            ->leftJoin('customers', 'customers.id', '=', 'accounts.customer_id')
            ->leftJoin('branches', 'branches.id', '=', 'accounts.branch_id')
            ->selectSub(
                PointHistory::withoutGlobalScopes()
                    ->from('point_histories as sum_ph')
                    ->selectRaw('COALESCE(SUM(sum_ph.points), 0)')
                    ->whereColumn('sum_ph.account_id', 'point_histories.account_id')
                    ->whereRaw('(sum_ph.year < point_histories.year OR (sum_ph.year = point_histories.year AND sum_ph.month < point_histories.month))')
                    ->whereNull('sum_ph.deleted_at'),
                'prev_points'
            )
            ->selectSub(
                PointHistory::withoutGlobalScopes()
                    ->from('point_histories as sum_ph')
                    ->selectRaw('COALESCE(SUM(sum_ph.points), 0)')
                    ->whereColumn('sum_ph.account_id', 'point_histories.account_id')
                    ->whereRaw('(sum_ph.year < point_histories.year OR (sum_ph.year = point_histories.year AND sum_ph.month <= point_histories.month))')
                    ->whereNull('sum_ph.deleted_at'),
                'total_points_sum'
            )
            ->whereNull('point_histories.deleted_at');

        // Event filtering matching TicketVerificationWidget
        if ($event) {
            if ($event->status == Event::STATUS_COMPLETED) {
                $query->whereIn('point_histories.account_id', function ($subQuery) use ($event) {
                    $subQuery->select('participants.account_id')
                        ->from('participants')
                        ->leftJoin('event_participant', 'event_participant.participant_id', '=', 'participants.id')
                        ->where(function ($q) use ($event) {
                            $q->where('participants.event_id', $event->id)
                                ->orWhere('event_participant.event_id', $event->id);
                        })
                        ->whereNull('participants.deleted_at');
                });
            } else {
                $query->whereIn('point_histories.account_id', function ($subQuery) use ($event) {
                    $subQuery->select('participants.account_id')
                        ->from('participants')
                        ->where('participants.event_id', $event->id)
                        ->whereNull('participants.deleted_at');
                });
            }
        }

        return $query;
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('period')
                ->label('Tanggal Data')
                ->state(fn ($record) => sprintf('%02d-%04d', $record->month, $record->year)),

            ExportColumn::make('customer_name')
                ->label('Nama'),

            ExportColumn::make('customer_cif')
                ->label('No CIF'),

            ExportColumn::make('account_number')
                ->label('No Account'),

            ExportColumn::make('branch_name')
                ->label('Cabang Pembuka Rekening')
                ->state(fn ($record) => $record->branch_company_book
                    ? "{$record->branch_company_book} - {$record->branch_name_value}"
                    : ''),

            ExportColumn::make('account_opening_date')
                ->label('Tanggal Buka'),

            ExportColumn::make('account_opening_balance')
                ->label('Saldo Tanggal Buka'),

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
                    if (!$record->account_opening_date) return 'ETB';
                    $openDate = Carbon::parse($record->account_opening_date);
                    return ($openDate->year == $record->year && $openDate->month == $record->month) ? 'NTB' : 'ETB';
                }),

            ExportColumn::make('flag_exclude')
                ->label('Flag Exclude')
                ->state(fn ($record) => $record->account_status === 'EXCLUDE' ? 'Y' : 'N'),

            ExportColumn::make('flag_inactive')
                ->label('Flag Inactive')
                ->state(fn ($record) => $record->account_status === 'INACTIVE' ? 'Y' : 'N'),

            ExportColumn::make('flag_confi')
                ->label('Flag Confi')
                ->state(fn ($record) => $record->account_status === 'CONFI' ? 'Y' : 'N'),
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
