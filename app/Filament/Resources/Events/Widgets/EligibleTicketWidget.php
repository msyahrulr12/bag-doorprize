<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Participant;
use App\Models\LotteryTicket;
use Carbon\Carbon;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EligibleTicketWidget extends TableWidget
{
    public ?Model $record = null;

    protected static ?string $heading = 'Eligible Ticket';

    public function getActiveMonths(): array
    {
        $event = $this->record;
        if (!$event) {
            return [];
        }

        $months = [];

        // Attempt to parse from event dates
        if ($event->event_started_at && $event->event_ended_at) {
            $start = Carbon::parse($event->event_started_at)->startOfMonth();
            $end = Carbon::parse($event->event_ended_at)->startOfMonth();

            while ($start->lessThanOrEqualTo($end)) {
                $months[] = [
                    'month' => (int) $start->format('m'),
                    'year' => (int) $start->format('Y'),
                ];
                $start->addMonth();
            }
        }

        // Fallback/merge with months that actually have active tickets for this event
        $ticketMonths = LotteryTicket::query()
            ->where('event_id', $event->id)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->select(['month', 'year'])
            ->distinct()
            ->get();

        foreach ($ticketMonths as $tm) {
            $exists = false;
            foreach ($months as $m) {
                if ($m['month'] === $tm->month && $m['year'] === $tm->year) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $months[] = [
                    'month' => $tm->month,
                    'year' => $tm->year,
                ];
            }
        }

        // Sort chronologically
        usort($months, function ($a, $b) {
            if ($a['year'] === $b['year']) {
                return $a['month'] <=> $b['month'];
            }
            return $a['year'] <=> $b['year'];
        });

        return $months;
    }

    public function table(Table $table): Table
    {
        $event = $this->record;
        $activeMonths = $this->getActiveMonths();

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
            TextColumn::make('created_at')
                ->label('Tanggal Data')
                ->dateTime('d-m-Y')
                ->sortable(),

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

            TextColumn::make('no_ktp')
                ->label('No KTP')
                ->state(fn() => 'N/A'),

            TextColumn::make('npwp')
                ->label('NPWP')
                ->state(fn() => 'N/A'),

            TextColumn::make('account.branch.branch_name')
                ->label('Cabang Pembuka Rekening')
                ->state(fn($record) => $record->account?->branch
                    ? "{$record->account->branch->company_book} - {$record->account->branch->branch_name}"
                    : '')
                ->sortable(),
        ];

        // Dynamic points columns
        foreach ($activeMonths as $am) {
            $monthLabel = $indonesianMonths[$am['month']] ?? Carbon::create()->month($am['month'])->format('M');
            $columnKey = "points_m{$am['month']}_y{$am['year']}";

            $columns[] = TextColumn::make($columnKey)
                ->label("Poin {$monthLabel}")
                ->state(function ($record) use ($am) {
                    $ticket = $record->lotteryTickets
                        ->where('status', LotteryTicket::STATUS_ACTIVE)
                        ->where('month', $am['month'])
                        ->where('year', $am['year'])
                        ->first();
                    return $ticket ? $ticket->total_points : 0;
                })
                ->numeric();
        }

        $columns[] = TextColumn::make('active_points')
            ->label('Total Poin')
            ->numeric()
            ->sortable();

        $columns[] = TextColumn::make('coupon_numbers')
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
            })
            ->wrap();

        return $table
            ->deferLoading()
            ->query(function () use ($event) {
                $query = Participant::query()
                    ->with([
                        'account.customer',
                        'account.branch',
                        'lotteryTickets' => function ($query) {
                            $query->where('status', LotteryTicket::STATUS_ACTIVE);
                        }
                    ])
                    ->whereHas('lotteryTickets', function ($query) {
                        $query->where('status', LotteryTicket::STATUS_ACTIVE);
                    })
                    ->withSum([
                        'lotteryTickets as active_points' => function ($query) {
                            $query->where('status', LotteryTicket::STATUS_ACTIVE);
                        }
                    ], 'total_points');

                if ($event) {
                    $statusEvent = $event->status;
                    if ($statusEvent == Event::STATUS_COMPLETED) {
                        $query->where(function ($q) use ($event) {
                            $q->where('participants.event_id', $event->id)
                                ->orWhereIn('participants.id', function ($subQuery) use ($event) {
                                    $subQuery->select('participant_id')
                                        ->from('event_participant')
                                        ->where('event_id', $event->id);
                                });
                        });
                    } else {
                        $query->where('participants.event_id', $event->id);
                    }
                }

                return $query;
            })
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('created_at', 'desc')
            ->columns($columns)
            ->filters([
                // Add filters if needed
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\EligibleTicketExporter::class)
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
