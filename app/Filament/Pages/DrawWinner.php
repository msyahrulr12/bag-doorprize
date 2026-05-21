<?php

namespace App\Filament\Pages;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Participant;
use App\Models\Winner;
use App\Models\TemporaryWinner;
use App\Models\Prize;
use App\Models\Setting;
use App\Models\BulkDrawBatch;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\WithoutScrolling;
use Illuminate\Support\Facades\DB;

#[WithoutScrolling]
class DrawWinner extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Gift;
    protected string $view = 'filament.pages.draw-winner';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'draw-winner/{eventPrize}';

    public ?int $eventPrizeId = null;
    public ?EventPrize $eventPrize = null;

    public ?array $searchData = [];
    public $winner = null;
    public $winners = [];

    public ?bool $enableRedraw = false;

    public $drawSessionId;
    public $notifWinnerExists = true;
    public ?int $splitDraw = null;
    public bool $isPreview = false;
    public bool $isDrawing = false;
    public $pendingWinners = [];
    public $pendingWinner = null;
    public $remainingQuantity = 0;

    #[Computed]
    public function paginatedWinners()
    {
        return Winner::where('event_prize_id', $this->eventPrizeId)->orderBy('id', 'desc')->paginate(30);
    }

    public function __construct()
    {
        $this->enableRedraw = Setting::where('key', 'activate_re_draw_and_confirm')->first()?->value ?? true;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_csv')
                ->label(__('Export CSV'))
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn() => $this->winner !== null || !empty($this->pendingWinners))
                ->action(fn() => $this->exportCsv()),
            \Filament\Actions\Action::make('export_excel')
                ->label(__('Export Excel'))
                ->color('emerald')
                ->icon('heroicon-o-table-cells')
                ->visible(fn() => $this->winner !== null || !empty($this->pendingWinners))
                ->action(fn() => $this->exportCsv('xls')),
            Action::make('back')
                ->label(__('Go Back'))
                ->url(url('/admin/event-prizes'))
                ->color('gray')
                ->button()
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function mount($eventPrize)
    {
        $this->eventPrizeId = $eventPrize->id;
        if (!$this->eventPrizeId) {
            abort(404);
        }

        $this->eventPrize = EventPrize::with(['event', 'prize'])->findOrFail($this->eventPrizeId);
        $this->splitDraw = $this->eventPrize->split_draw;
        $this->remainingQuantity = $this->eventPrize->remaining_quantity;

        $this->checkDrawSession();

        $this->form->fill([
            'draw_session_id' => $this->drawSessionId
        ]);

        $this->checkWinner();
    }

    private function checkDrawSession()
    {
        $this->drawSessionId = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', DrawSession::STATUS_ACTIVE)
            ->where('started_at', '<=', now())
            ->where('ended_at', '>=', now())->first()?->id;
    }

    private function checkWinner()
    {
        $this->isPreview = false;

        // Check temporary winners
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrizeId)
            ->get();

        if ($tempWinners->count() > 0) {
            $this->pendingWinners = $tempWinners->map(fn($tw) => $tw->getData())->toArray();
            $this->winner = end($this->pendingWinners);
            $this->isPreview = true;
            $this->winners = collect($this->pendingWinners)->split(2)->toArray();
        }

        $paginatedWinners = $this->paginatedWinners;

        if ($paginatedWinners->count() > 0) {
            $winnerExists = $paginatedWinners->first();

            if ($this->notifWinnerExists && !$this->winner && !$this->isPreview) {
                Notification::make()
                    ->info()
                    ->title('Winners Found')
                    ->body("There are already " . $paginatedWinners->total() . " winners recorded for this prize.")
                    ->send();
                $this->notifWinnerExists = false;
            }

            if (!$this->isPreview) {
                $winnerExists->load(['participant.account.branch', 'participant.account.customer']);
                $this->winner = [
                    'id' => $winnerExists->id,
                    'ticket' => $winnerExists->lotteryTicket?->toArray() ?? [],
                    'participant' => $winnerExists->participant,
                    'customer' => $winnerExists->participant?->account?->customer,
                    'lucky_number' => $winnerExists->winning_number,
                    'winning_number' => $winnerExists->range_start === $winnerExists->range_end
                        ? $winnerExists->range_start
                        : "{$winnerExists->range_start} - {$winnerExists->range_end}",
                    'draw_session_id' => $winnerExists->draw_session_id,
                    'branch_name' => $winnerExists->branch_name,
                    'region' => $winnerExists->branch_region,
                    'drawn_at' => $winnerExists->created_at->format('Y-m-d H:i:s'),
                ];
                $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(2)->toArray();
            }

            return true;
        }

        if (!$this->isPreview) {
            $this->winners = [];
            $this->winner = null;
        }
        return false;
    }

    public function updatedPage()
    {
        if (!$this->isPreview) {
            $this->checkWinner();
            $this->dispatch('scroll-to-results');
        }
    }

    public function form(Schema $schema): Schema
    {
        $sessions = DrawSession::where('event_id', $this->eventPrize->event_id)->get();

        return $schema
            ->components([
                Select::make('draw_session_id')
                    ->label('Draw Session')
                    ->options($sessions->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->helperText('Select the active drawing session for this event.')
                    ->default(fn() => $this->drawSessionId)
                    ->disableOptionWhen(function (string $value) use ($sessions) {
                        $session = $sessions->find($value);
                        if (!$session)
                            return true;
                        $now = now();
                        return $session->status !== DrawSession::STATUS_ACTIVE || $now->lt($session->started_at) || $now->gt($session->ended_at);
                    })
                    ->disabled(fn(): bool => $this->isPreview),
                Hidden::make('draw_session_id')
                    ->default(fn() => $this->drawSessionId),
                TextInput::make('split_draw')
                    ->label('Split Draw')
                    ->helperText('Number of winners to reveal together.')
                    ->numeric()
                    ->readOnly()
                    ->formatStateUsing(fn() => $this->eventPrize->split_draw)
                    ->default(fn() => $this->eventPrize->split_draw),
            ])
            ->statePath('searchData');
    }

    private function generateLuckyNumber($ticket): string
    {
        $start = $ticket->range_start;
        $end = $ticket->range_end;
        if ($start === $end)
            return $start;
        if (preg_match('/^([A-Z]*)(\d+)$/', $start, $startMatch) && preg_match('/^([A-Z]*)(\d+)$/', $end, $endMatch)) {
            $prefix = $startMatch[1];
            $startNum = (int) $startMatch[2];
            $endNum = (int) $endMatch[2];
            $randomNum = mt_rand($startNum, $endNum);
            return $prefix . str_pad($randomNum, strlen($startMatch[2]), '0', STR_PAD_LEFT);
        }
        return $start;
    }

    public function draw()
    {
        $this->eventPrize->refresh();
        if ($this->eventPrize->remaining_quantity <= 0) {
            Notification::make()->danger()->title('Prize Exhausted')->send();
            return;
        }

        $splitDraw = (int) ($this->eventPrize->split_draw ?? 1);
        if ($splitDraw <= 0)
            $splitDraw = 1;

        $batchWinners = [];
        $eventId = $this->eventPrize->event_id;

        $weightsSetting = Setting::where('key', 'region_weights')->first();
        $weights = $weightsSetting ? json_decode($weightsSetting->value, true) : [
            'JABODETABEK' => 50,
            'JABAR JATENG JATIM' => 15,
            'SUMATERA' => 15,
            'BALI, NTT, MALUKU' => 7,
            'SULAWESI' => 7,
            'KALIMANTAN' => 6,
            'LAINNYA' => 0
        ];

        for ($i = 0; $i < $splitDraw; $i++) {
            $this->eventPrize->refresh();
            if ($this->eventPrize->remaining_quantity <= count($batchWinners))
                break;

            $rand = mt_rand(1, 100);
            $targetRegion = 'Lainnya';
            $cumulative = 0;
            foreach ($weights as $region => $weight) {
                $cumulative += $weight;
                if ($rand <= $cumulative) {
                    $targetRegion = $region;
                    break;
                }
            }

            $winnerTicket = $this->findWinnerInRegion($targetRegion, $eventId, array_column($batchWinners, 'customer_id'));
            if (!$winnerTicket) {
                $otherRegions = array_keys(collect($weights)->sortByDesc(fn($w) => $w)->toArray());
                foreach ($otherRegions as $region) {
                    if ($region === $targetRegion)
                        continue;
                    $winnerTicket = $this->findWinnerInRegion($region, $eventId, array_column($batchWinners, 'customer_id'));
                    if ($winnerTicket)
                        break;
                }
            }
            if (!$winnerTicket)
                $winnerTicket = $this->findWinnerInRegion(null, $eventId, array_column($batchWinners, 'customer_id'));

            if (!$winnerTicket)
                break;

            $winnerTicket->load(['participant.account.branch', 'participant.account.customer']);
            $luckyNumber = $this->generateLuckyNumber($winnerTicket);

            $batchWinners[] = [
                'ticket' => $winnerTicket->toArray(),
                'participant' => $winnerTicket->participant->toArray(),
                'customer' => $winnerTicket->participant->account->customer->toArray(),
                'customer_id' => $winnerTicket->participant->account->customer->id,
                'lottery_ticket_id' => $winnerTicket->id,
                'participant_id' => $winnerTicket->participant_id,
                'lucky_number' => $luckyNumber,
                'winning_number' => $winnerTicket->range_start === $winnerTicket->range_end
                    ? $winnerTicket->range_start
                    : "{$winnerTicket->range_start} - {$winnerTicket->range_end}",
                'draw_session_id' => $this->drawSessionId,
                'branch_name' => $winnerTicket->participant->account->branch->branch_name ?? 'N/A',
                'region' => $winnerTicket->participant->account->branch->region ?? 'N/A'
            ];
        }

        if (empty($batchWinners)) {
            Notification::make()->danger()->title('No Eligible Winner Found')->send();
            return;
        }

        $this->pendingWinners = $batchWinners;
        $this->pendingWinner = $batchWinners[0];
        $this->isDrawing = true;
        $this->winner = null;

        $this->dispatch('start-drawing-animation', ['luckyNumber' => $this->pendingWinner['lucky_number']]);
    }

    public function finishDrawing()
    {
        if (empty($this->pendingWinners))
            return;

        $this->isDrawing = false;
        $this->isPreview = true;

        $winnersToSave = [];
        foreach ($this->pendingWinners as $w) {
            $participant = Participant::with(['account.branch', 'account.customer'])->findOrFail($w['participant_id']);
            $ticket = LotteryTicket::findOrFail($w['lottery_ticket_id']);

            $winnersToSave[] = [
                'participant_id' => $participant->id,
                'participant_cif' => $participant->participant_cif,
                'participant_account_number' => $participant->participant_account_number,
                'participant_name' => $participant->participant_name,
                'participant_email' => $participant->participant_email,
                'participant_phone_number' => $participant->participant_phone_number,
                'event_prize_id' => $this->eventPrize->id,
                'draw_session_id' => $this->drawSessionId,
                'winning_number' => $w['lucky_number'],
                'drawn_at' => now(),
                'drawn_by' => Auth::user()->name ?? 'Admin',
                'lottery_ticket_id' => $ticket->id,
                'total_points' => $ticket->total_points ?? 0,
                'range_start' => $ticket->range_start ?? 'N/A',
                'range_end' => $ticket->range_end ?? 'N/A',
                'status' => Winner::STATUS_PENDING,
                'branch_id' => $participant->account->branch_id,
                'branch_code' => $participant->account->branch->branch_code,
                'branch_name' => $participant->account->branch->branch_name,
                'branch_company_book' => $participant->account->branch->company_book,
                'branch_region' => $participant->account->branch->region,
                'account_status' => $participant->account->status,
            ];
        }

        if ($this->enableRedraw) {
            foreach ($winnersToSave as $wData) {
                TemporaryWinner::create($wData);
            }
            $this->checkWinner();
            Notification::make()->success()->title('Winners picked and staged for review')->send();
        } else {
            foreach ($winnersToSave as $wData) {
                $fullData = array_merge($wData, [
                    'prize_name' => $this->eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
                    'prize_total_quantity' => $this->eventPrize->total_quantity,
                    'prize_value' => $this->eventPrize->prize->value,
                    'prize_description' => $this->eventPrize->prize->description,
                    'event_code' => $this->eventPrize->event->event_code,
                    'event_name' => $this->eventPrize->event->event_name,
                ]);
                Winner::create($fullData);
                $this->eventPrize->decrement('remaining_quantity');
            }
            Notification::make()->success()->title('Winners Confirmed!')->send();
            $this->winner = null;
            $this->isPreview = false;
            $this->checkWinner();
        }
    }

    public function confirmWinners()
    {
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrizeId)
            ->get();

        if ($tempWinners->isEmpty())
            return;

        DB::beginTransaction();
        try {
            foreach ($tempWinners as $tw) {
                $this->eventPrize->refresh();
                if ($this->eventPrize->remaining_quantity <= 0)
                    throw new \Exception("Prize exhausted.");

                $wData = $tw->toArray();
                unset($wData['id'], $wData['created_at'], $wData['updated_at'], $wData['deleted_at']);

                $fullData = array_merge($wData, [
                    'prize_name' => $this->eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
                    'prize_total_quantity' => $this->eventPrize->total_quantity,
                    'prize_value' => $this->eventPrize->prize->value,
                    'prize_description' => $this->eventPrize->prize->description,
                    'event_code' => $this->eventPrize->event->event_code,
                    'event_name' => $this->eventPrize->event->event_name,
                ]);

                Winner::create($fullData);
                $this->eventPrize->decrement('remaining_quantity');
                $tw->delete();
            }
            DB::commit();

            Notification::make()->success()->title('All winners confirmed!')->send();
            $this->winner = null;
            $this->isPreview = false;
            $this->pendingWinners = [];
            $this->checkWinner();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()->title('Confirmation failed')->body($e->getMessage())->send();
        }
    }

    public function resetWinners()
    {
        TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrizeId)
            ->delete();

        $this->winner = null;
        $this->isPreview = false;
        $this->pendingWinners = [];
        $this->checkWinner();

        Notification::make()->info()->title('Winners reset. You can draw again.')->send();
    }

    public function confirmWinner()
    {
        $this->confirmWinners();
    }

    private function findWinnerInRegion(?string $region, int $eventId, array $excludeCustomerIds = []): ?LotteryTicket
    {
        $query = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->whereIn('participant_id', function ($q) use ($eventId) {
                $q->select('participant_id')
                  ->from('lottery_tickets')
                  ->where('event_id', $eventId)
                  ->where('status', LotteryTicket::STATUS_ACTIVE)
                  ->whereNull('deleted_at')
                  ->groupBy('participant_id')
                  ->havingRaw('SUM(total_points) >= ?', [$this->eventPrize->min_points_required]);
            })
            ->whereHas('participant.account.customer', function ($q) use ($eventId, $excludeCustomerIds) {
                $q->whereDoesntHave('accounts.participants.winners', function ($wq) {
                    $wq->where('draw_session_id', $this->drawSessionId);
                });
                if (!empty($excludeCustomerIds))
                    $q->whereNotIn('customers.id', $excludeCustomerIds);

                $pendingBatchCustomerIds = BulkDrawBatch::where('draw_session_id', $this->drawSessionId)
                    ->whereIn('status', ['PENDING', 'PROCESSING', 'COMPLETED'])
                    ->get()->pluck('results')->flatten(1)->pluck('customer.id')->unique()->filter()->toArray();

                if (!empty($pendingBatchCustomerIds))
                    $q->whereNotIn('customers.id', $pendingBatchCustomerIds);
            });

        if ($region) {
            $query->whereHas('participant.account.branch', fn($q) => $q->whereRaw('UPPER(region) = ?', [strtoupper(trim($region))]));
        }

        $tickets = $query->get();
        if ($tickets->isEmpty())
            return null;

        $totalPoints = $tickets->sum('total_points');
        $winningOffset = mt_rand(1, $totalPoints);
        $currentOffset = 0;
        foreach ($tickets as $ticket) {
            $currentOffset += $ticket->total_points;
            if ($winningOffset <= $currentOffset)
                return $ticket;
        }
        return $tickets->first();
    }

    public function exportCsv($extension = 'csv')
    {
        $winnersToExport = [];
        if (!empty($this->pendingWinners))
            $winnersToExport = $this->pendingWinners;
        elseif ($this->winner)
            $winnersToExport[] = $this->winner;

        if (empty($winnersToExport))
            return null;

        $filename = "winner_" . now()->format('Ymd_His') . "." . $extension;

        if ($extension === 'xls') {
            return response()->streamDownload(function () use ($winnersToExport) {
                echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head><meta http-equiv="content-type" content="text/html; charset=utf-8"/></head>
                <body><table border="1">
                    <thead>
                        <tr style="background-color: #f3f4f6;">
                            <th>CIF</th><th>Account Number</th><th>Name</th><th>Prize</th><th>Lucky Number</th><th>Points</th><th>Branch</th><th>Drawn At</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($winnersToExport as $w) {
                    $p = $w['participant'];
                    echo '<tr>
                        <td>' . (is_array($p) ? ($p['participant_cif'] ?? 'N/A') : $p->participant_cif) . '</td>
                        <td>' . (is_array($p) ? ($p['participant_account_number'] ?? 'N/A') : $p->participant_account_number) . '</td>
                        <td>' . (is_array($p) ? ($p['participant_name'] ?? 'N/A') : $p->participant_name) . '</td>
                        <td>' . $this->eventPrize->prize->prize_name . '</td>
                        <td>' . $w['lucky_number'] . '</td>
                        <td>' . (is_array($w['ticket']) ? ($w['ticket']['total_points'] ?? 0) : $w['ticket']->total_points) . '</td>
                        <td>' . $w['branch_name'] . '</td>
                        <td>' . now()->format('Y-m-d H:i:s') . '</td>
                    </tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($winnersToExport) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['CIF', 'Account Number', 'Name', 'Prize', 'Lucky Number', 'Points', 'Branch', 'Drawn At']);
            foreach ($winnersToExport as $w) {
                $p = $w['participant'];
                fputcsv($file, [
                    is_array($p) ? ($p['participant_cif'] ?? 'N/A') : $p->participant_cif,
                    is_array($p) ? ($p['participant_account_number'] ?? 'N/A') : $p->participant_account_number,
                    is_array($p) ? ($p['participant_name'] ?? 'N/A') : $p->participant_name,
                    $this->eventPrize->prize->prize_name,
                    $w['lucky_number'],
                    is_array($w['ticket']) ? ($w['ticket']['total_points'] ?? 0) : $w['ticket']->total_points,
                    $w['branch_name'],
                    now()->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, $filename);
    }

    public function exportExcel()
    {
        return $this->exportCsv('xls');
    }
}
