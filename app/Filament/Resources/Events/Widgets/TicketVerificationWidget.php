<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use App\Models\Participant;
use App\Models\PointHistory;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TicketVerificationWidget extends TableWidget
{
    public ?Model $record = null;

    protected static ?string $heading = 'Ticket Verification';

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(function () {
                $prevAmountSubquery = PointHistory::query()
                    ->from('point_histories as prev_ph')
                    ->select('amount')
                    ->whereColumn('prev_ph.account_id', 'point_histories.account_id')
                    ->whereRaw('prev_ph.month = (CASE WHEN point_histories.month = 1 THEN 12 ELSE point_histories.month - 1 END)')
                    ->whereRaw('prev_ph.year = (CASE WHEN point_histories.month = 1 THEN point_histories.year - 1 ELSE point_histories.year END)')
                    ->whereNull('prev_ph.deleted_at')
                    ->limit(1);

                $prevPointsSubquery = PointHistory::query()
                    ->from('point_histories as sum_ph')
                    ->selectRaw('COALESCE(SUM(sum_ph.points), 0)')
                    ->whereColumn('sum_ph.account_id', 'point_histories.account_id')
                    ->whereRaw('(sum_ph.year < point_histories.year OR (sum_ph.year = point_histories.year AND sum_ph.month < point_histories.month))')
                    ->whereNull('sum_ph.deleted_at');

                $totalPointsSubquery = PointHistory::query()
                    ->from('point_histories as sum_ph')
                    ->selectRaw('COALESCE(SUM(sum_ph.points), 0)')
                    ->whereColumn('sum_ph.account_id', 'point_histories.account_id')
                    ->whereRaw('(sum_ph.year < point_histories.year OR (sum_ph.year = point_histories.year AND sum_ph.month <= point_histories.month))')
                    ->whereNull('sum_ph.deleted_at');

                $event = $this->record;

                return PointHistory::query()
                    ->with(['account.customer', 'account.branch'])
                    ->select([
                        'point_histories.*',
                    ])
                    ->selectSub($prevAmountSubquery, 'prev_amount')
                    ->selectSub($prevPointsSubquery, 'prev_points')
                    ->selectSub($totalPointsSubquery, 'total_points_sum')
                    ->whereIn('point_histories.account_id', function ($subQuery) use ($event) {
                        if (!$event) {
                            $subQuery->select('account_id')
                                ->from('participants')
                                ->whereRaw('1 = 0');
                            return;
                        }

                        $statusEvent = $event->status;
                        if ($statusEvent == Event::STATUS_COMPLETED) {
                            $subQuery->select('participants.account_id')
                                ->from('participants')
                                ->leftJoin('event_participant', 'event_participant.participant_id', '=', 'participants.id')
                                ->where(function ($q) use ($event) {
                                    $q->where('participants.event_id', $event->id)
                                        ->orWhere('event_participant.event_id', $event->id);
                                })
                                ->whereNull('participants.deleted_at');
                        } else {
                            $subQuery->select('participants.account_id')
                                ->from('participants')
                                ->where('participants.event_id', $event->id)
                                ->whereNull('participants.deleted_at');
                        }
                    });
            })
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('point_histories.created_at', 'desc')
            ->columns([
                TextColumn::make('period')
                    ->label('Tanggal Data')
                    ->state(fn($record) => sprintf('%02d-%04d', $record->month, $record->year))
                    ->sortable(['point_histories.year', 'point_histories.month']),

                TextColumn::make('account.customer.name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account.customer.cif')
                    ->label('No CIF')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account.account_number')
                    ->label('No Account')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account.branch.branch_name')
                    ->label('Cabang Pembuka Rekening')
                    ->state(fn($record) => $record->account?->branch ? "{$record->account->branch->company_book} - {$record->account->branch->branch_name}" : '')
                    ->sortable(),

                TextColumn::make('account.account_opening_date')
                    ->label('Tanggal Buka')
                    ->date()
                    ->sortable(),

                TextColumn::make('account.account_opening_balance')
                    ->label('Saldo Tanggal Buka')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('prev_amount')
                    ->label('Average Balance Bulan Lalu')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Average Bulan Berjalan')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('growth_amount')
                    ->label('Growth Bulan Berjalan')
                    ->state(fn($record) => ($record->amount ?? 0) - ($record->prev_amount ?? 0))
                    ->money('IDR', locale: 'id')
                    ->sortable(query: function (Builder $query, string $direction) {
                        $prevAmountSubquery = PointHistory::query()
                            ->from('point_histories as prev_ph')
                            ->select('amount')
                            ->whereColumn('prev_ph.account_id', 'point_histories.account_id')
                            ->whereRaw('prev_ph.month = (CASE WHEN point_histories.month = 1 THEN 12 ELSE point_histories.month - 1 END)')
                            ->whereRaw('prev_ph.year = (CASE WHEN point_histories.month = 1 THEN point_histories.year - 1 ELSE point_histories.year END)')
                            ->whereNull('prev_ph.deleted_at')
                            ->limit(1);

                        $query->orderBy(
                            DB::raw('(point_histories.amount - COALESCE((' . $prevAmountSubquery->toSql() . '), 0))'),
                            $direction
                        );
                    }),

                TextColumn::make('prev_points')
                    ->label('Akumulasi Kupon Bulan Lalu')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('points')
                    ->label('Kupon Bulan Berjalan')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_points_sum')
                    ->label('Total Kupon')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('flag_cif')
                    ->label('Flag CIF (NTB / ETB)')
                    ->state(function ($record) {
                        if (!$record->account?->account_opening_date) return 'ETB';
                        $openDate = Carbon::parse($record->account->account_opening_date);
                        return ($openDate->year == $record->year && $openDate->month == $record->month) ? 'NTB' : 'ETB';
                    })
                    ->badge()
                    ->color(fn($state) => $state === 'NTB' ? 'success' : 'info'),

                TextColumn::make('flag_exclude')
                    ->label('Flag Exclude')
                    ->state(fn($record) => $record->account?->status === 'EXCLUDE' ? 'Y' : 'N')
                    ->badge()
                    ->color(fn($state) => $state === 'Y' ? 'danger' : 'gray'),

                TextColumn::make('flag_inactive')
                    ->label('Flag Inactive')
                    ->state(fn($record) => $record->account?->status === 'INACTIVE' ? 'Y' : 'N')
                    ->badge()
                    ->color(fn($state) => $state === 'Y' ? 'warning' : 'gray'),

                TextColumn::make('flag_confi')
                    ->label('Flag Confi')
                    ->state(fn($record) => $record->account?->status === 'CONFI' ? 'Y' : 'N')
                    ->badge()
                    ->color(fn($state) => $state === 'Y' ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Month')
                    ->options([
                        1 => 'January',
                        2 => 'February',
                        3 => 'March',
                        4 => 'April',
                        5 => 'May',
                        6 => 'June',
                        7 => 'July',
                        8 => 'August',
                        9 => 'September',
                        10 => 'October',
                        11 => 'November',
                        12 => 'December',
                    ])
                    ->query(fn(Builder $query, array $data) => $data['value'] ? $query->where('point_histories.month', $data['value']) : $query),

                SelectFilter::make('year')
                    ->label('Year')
                    ->options(fn() => array_combine(range(2025, 2035), range(2025, 2035)))
                    ->query(fn(Builder $query, array $data) => $data['value'] ? $query->where('point_histories.year', $data['value']) : $query),

                SelectFilter::make('flag_cif')
                    ->label('Flag CIF')
                    ->options([
                        'NTB' => 'NTB',
                        'ETB' => 'ETB',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['value'] === 'NTB') {
                            $query->whereHas('account', function (Builder $q) {
                                $q->whereRaw('EXTRACT(YEAR FROM account_opening_date) = point_histories.year AND EXTRACT(MONTH FROM account_opening_date) = point_histories.month');
                            });
                        } elseif ($data['value'] === 'ETB') {
                            $query->whereHas('account', function (Builder $q) {
                                $q->whereRaw('(account_opening_date IS NULL OR EXTRACT(YEAR FROM account_opening_date) != point_histories.year OR EXTRACT(MONTH FROM account_opening_date) != point_histories.month)');
                            });
                        }
                    }),

                SelectFilter::make('account_status')
                    ->label('Account Status')
                    ->options([
                        'ACTIVE' => 'Active',
                        'INACTIVE' => 'Inactive',
                        'EXCLUDE' => 'Exclude',
                        'CONFI' => 'Confi',
                    ])
                    ->query(fn(Builder $query, array $data) => $data['value'] ? $query->whereHas('account', fn($q) => $q->where('status', $data['value'])) : $query),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\TicketVerificationExporter::class)
                    ->options(fn() => [
                        'event_id' => $this->record?->id,
                    ])
                    ->label('Export CSV/Excel')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->chunkSize(20000),
            ]);
    }
}
