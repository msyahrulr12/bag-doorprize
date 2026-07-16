<?php

namespace App\Filament\Exports;

use App\Models\Participant;
use App\Models\LotteryTicket;
use App\Models\Event;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class EligibleTicketExporter extends Exporter
{
    protected static ?string $model = Participant::class;

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
        // Check route first (reliable in HTTP context, avoids stale static state under Octane)
        $event = request()->route('record');
        if ($event) {
            return $event instanceof Event ? $event->id : (is_numeric($event) ? (int) $event : null);
        }

        // Fallback to static options (reliable in queue context, set by constructor)
        if (isset(self::$staticOptions['event_id'])) {
            return (int) self::$staticOptions['event_id'];
        }

        return null;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        \Illuminate\Support\Facades\DB::connection()->disableQueryLog();

        // Build a fresh, deterministic query matching EligibleTicketWidget exactly
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

        $eventId = self::getEventId();
        if ($eventId) {
            $event = Event::find($eventId);
            if ($event) {
                if ($event->status == Event::STATUS_COMPLETED) {
                    $query->where(function ($q) use ($event) {
                        $q->where('participants.event_id', $event->id)
                            ->orWhereIn('participants.id', function ($subQuery) use ($event) {
                                $subQuery->select('participant_id')
                                    ->from('event_participant')
                                    ->where('event_id', $event->id);
                            });
                    });
                } else {
                    $query->where('participants.event_id', $eventId);
                }
            }
        }

        return $query;
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
        $eventId = self::getEventId();
        $event = $eventId ? Event::find($eventId) : null;

        if ($event) {
            // Attempt to parse from event dates
            if ($event->event_started_at && $event->event_ended_at) {
                $start = Carbon::parse($event->event_started_at)->startOfMonth();
                $end = Carbon::parse($event->event_ended_at)->startOfMonth();

                while ($start->lessThanOrEqualTo($end)) {
                    $activeMonths[] = (object) [
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
                foreach ($activeMonths as $m) {
                    if ($m->month === $tm->month && $m->year === $tm->year) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $activeMonths[] = (object) [
                        'month' => $tm->month,
                        'year' => $tm->year,
                    ];
                }
            }

            // Sort chronologically
            usort($activeMonths, function ($a, $b) {
                if ($a->year === $b->year) {
                    return $a->month <=> $b->month;
                }
                return $a->year <=> $b->year;
            });
        } else {
            try {
                $activeMonths = LotteryTicket::query()
                    ->where('status', LotteryTicket::STATUS_ACTIVE)
                    ->select(['month', 'year'])
                    ->distinct()
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();
            } catch (\Exception $e) {
                dd($e);
                // fallback
            }
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
                    // if (empty($ticket->range_start) || empty($ticket->range_end)) {
                    //     continue;
                    // }
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
