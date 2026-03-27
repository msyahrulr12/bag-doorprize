<?php

namespace App\Filament\Pages;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Participant;
use App\Models\Winner;
use App\Models\Prize;
use App\Models\Setting;
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
    public $pendingWinner = null;
    public $remainingQuantity = 0;

    #[Computed]
    public function paginatedWinners()
    {
        return Winner::where('event_prize_id', $this->eventPrizeId)->orderBy('id', 'desc')->paginate(30);
    }

    public function __construct()
    {
        $this->enableRedraw = Setting::where('key', 'activate_re_draw_and_confirm')->first()->value;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Go Back'))
                ->url(url()->previous())
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
        $this->remainingQuantity = $eventPrize?->remaining_quantity;

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
        $paginatedWinners = $this->paginatedWinners;

        if ($paginatedWinners->count() > 0) {
            $winnerExists = $paginatedWinners->first();
            $winnerExists->load(['participant.account.branch', 'participant.account.customer']);

            if ($this->notifWinnerExists && !$this->winner) {
                Notification::make()
                    ->info()
                    ->title('Winners Found')
                    ->body("There are already " . $paginatedWinners->total() . " winners recorded for this prize.")
                    ->send();
            }

            // Set the most recent winner for the large card
            $this->winner = [
                'id' => $winnerExists->id,
                'ticket' => $winnerExists->lotteryTicket?->toArray() ?? [],
                'participant' => $winnerExists->participant,           // keep as Eloquent model — blade uses ->participant_name etc.
                'customer' => $winnerExists->participant?->account?->customer, // keep as Eloquent model
                'lucky_number' => $winnerExists->winning_number,
                'winning_number' => $winnerExists->range_start === $winnerExists->range_end
                    ? $winnerExists->range_start
                    : "{$winnerExists->range_start} - {$winnerExists->range_end}",
                'draw_session_id' => $winnerExists->draw_session_id,
                'branch_name' => $winnerExists->branch_name,
                'region' => $winnerExists->branch_region,
                'drawn_at' => $winnerExists->created_at->format('Y-m-d H:i:s'),
            ];

            // For the table display
            $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(2)->toArray();

            return true;
        }

        $this->winners = [];
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
                        $startedAt = \Carbon\Carbon::parse($session->started_at);
                        $endedAt = \Carbon\Carbon::parse($session->ended_at);

                        return $session->status !== DrawSession::STATUS_ACTIVE ||
                            $now->lt($startedAt) ||
                            $now->gt($endedAt);
                    })
                    ->disabled(fn(): bool => $this->winners >= $this?->eventPrize?->total_quantity),
                Hidden::make('draw_session_id')
                    ->default(fn() => $this->drawSessionId)
                    ->disabled(fn(): bool => $this->winners >= $this?->eventPrize?->total_quantity),
                TextInput::make('split_draw')
                    ->label('Split Draw')
                    ->helperText('Number of winners to reveal together.')
                    ->numeric()
                    ->readOnly()
                    ->formatStateUsing(fn() => $this->eventPrize->split_draw)
                    ->default(fn() => $this->eventPrize->remaining_quantity),
            ])
            ->statePath('searchData');
    }

    private function generateLuckyNumber($ticket): string
    {
        $start = $ticket->range_start;
        $end = $ticket->range_end;

        if ($start === $end)
            return $start;

        // Assumes format like A12345678 (Prefix + Digits)
        if (
            preg_match('/^([A-Z]*)(\d+)$/', $start, $startMatch) &&
            preg_match('/^([A-Z]*)(\d+)$/', $end, $endMatch)
        ) {

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
        $data = $this->form->getState();
        $eventId = $this->eventPrize->event_id;

        // 1. Define region weights from Setting
        $weightsSetting = Setting::where('key', 'region_weights')->first();
        $weights = $weightsSetting ? json_decode($weightsSetting->value, true) : [
            'Jawa' => 50,
            'Sumatera' => 20,
            'Sulawesi' => 20,
            'Lainnya' => 10,
        ];

        // 2. Pick a target region based on weights
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

        // 3. Find eligible winners in the target region
        $winnerTicket = $this->findWinnerInRegion($targetRegion, $eventId);

        // 4. Fallback: If no winner in target region, try other regions in order of weight
        if (!$winnerTicket) {
            $otherRegions = array_keys(collect($weights)->sortByDesc(fn($w) => $w)->toArray());
            foreach ($otherRegions as $region) {
                if ($region === $targetRegion)
                    continue;
                $winnerTicket = $this->findWinnerInRegion($region, $eventId);
                if ($winnerTicket)
                    break;
            }
        }

        // 5. Final Fallback: If still no winner, pick any eligible ticket regardless of region
        if (!$winnerTicket) {
            $winnerTicket = $this->findWinnerInRegion(null, $eventId);
        }

        if (!$winnerTicket) {
            Notification::make()
                ->danger()
                ->title('No Eligible Winner Found')
                ->body("Could not find any eligible participant who meets the requirements.")
                ->send();
            return;
        }

        $winnerTicket->load(['participant.account.branch', 'participant.account.customer']);
        $luckyNumber = $this->generateLuckyNumber($winnerTicket);

        $participant = $winnerTicket->participant;
        $customer = $participant->account->customer;

        $this->isPreview = true;
        // Store as pending to allow animation
        $this->pendingWinner = [
            'ticket' => $winnerTicket,
            'participant' => $participant,
            'customer' => $customer,
            'lucky_number' => $luckyNumber,
            'winning_number' => $winnerTicket->range_start === $winnerTicket->range_end
                ? $winnerTicket->range_start
                : "{$winnerTicket->range_start} - {$winnerTicket->range_end}",
            'draw_session_id' => isset($data['draw_session_id']) ? $data['draw_session_id'] : $this->drawSessionId,
            'branch_name' => $participant->account->branch->branch_name ?? 'N/A',
            'region' => $participant->account->branch->region ?? 'N/A'
        ];

        $this->isDrawing = true;
        $this->winner = null; // Hide current result if any

        $this->dispatch('start-drawing-animation', ['luckyNumber' => $luckyNumber]);
    }

    public function finishDrawing()
    {
        if (!$this->pendingWinner)
            return;

        $this->winner = $this->pendingWinner;
        $this->pendingWinner = null;
        $this->isDrawing = false;
        $this->isPreview = true;

        if (!$this->enableRedraw) {
            $this->confirmWinner();
            $this->notifWinnerExists = false;
            $this->checkWinner();
        }
    }

    /**
     * @param string|null $region
     * @param int $eventId
     * @return LotteryTicket|null
     */
    private function findWinnerInRegion(?string $region, int $eventId): ?LotteryTicket
    {
        $query = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('total_points', '>=', $this->eventPrize->min_points_required)
            ->where('status', LotteryTicket::STATUS_ACTIVE) // Only active tickets
            ->whereHas('participant.account.customer', function ($q) use ($eventId) {
                // Customer must not have won in this event before
                $q->whereDoesntHave('accounts.participants.winners', function ($wq) use ($eventId) {
                    $wq->whereHas('eventPrize', fn($eq) => $eq->where('event_prizes.event_id', $eventId));
                });

                // Customer must not be in any pending/processing bulk draw batches for this event
                $pendingBatchCustomerIds = \App\Models\BulkDrawBatch::whereHas('eventPrize', fn($ep) => $ep->where('event_id', $eventId))
                    ->whereIn('status', ['PENDING', 'PROCESSING', 'COMPLETED'])
                    ->get()
                    ->pluck('results')
                    ->flatten(1)
                    ->pluck('customer.id')
                    ->unique()
                    ->filter()
                    ->toArray();

                if (!empty($pendingBatchCustomerIds)) {
                    $q->whereNotIn('customers.id', $pendingBatchCustomerIds);
                }
            });

        if ($region) {
            $query->whereHas('participant.account.branch', function ($q) use ($region) {
                $q->where('region', $region);
            });
        }

        $tickets = $query->get();
        if ($tickets->isEmpty())
            return null;

        // Weighted random selection based on total_points
        $totalPoints = $tickets->sum('total_points');
        $winningOffset = mt_rand(1, $totalPoints);

        $currentOffset = 0;
        foreach ($tickets as $ticket) {
            /** @var LotteryTicket $ticket */
            $currentOffset += $ticket->total_points;
            if ($winningOffset <= $currentOffset) {
                return $ticket;
            }
        }

        return $tickets->first();
    }

    public function clearWinner()
    {
        $this->winner = null;
    }

    public function confirmWinner()
    {
        if (!$this->winner)
            return;

        // Re-check availability
        $this->eventPrize->refresh();
        if ($this->eventPrize->remaining_quantity <= 0) {
            Notification::make()
                ->danger()
                ->title('Prize Exhausted')
                ->body("Sorry, this prize is no longer available.")
                ->send();
            $this->winner = null;
            return;
        }

        $winnerData = $this->winner;
        $ticketId = $winnerData['ticket']['id'] ?? null;

        // participant and customer may be Eloquent models (set by draw()) or arrays (set by checkWinner())
        $participant = $winnerData['participant'];
        $customer = $winnerData['customer'];

        $participantId = is_array($participant) ? ($participant['id'] ?? null) : $participant?->id;
        $customerId = is_array($customer) ? ($customer['id'] ?? null) : $customer?->id;

        if (!$participantId) {
            Notification::make()->danger()->title('Invalid participant data.')->send();
            return;
        }

        // Re-check customer eligibility one last time
        $alreadyWon = Winner::whereHas('eventPrize', fn($q) => $q->where('event_prizes.event_id', $this->eventPrize->event_id))
            ->whereHas('participant.account', fn($q) => $q->where('customer_id', $customerId))
            ->exists();

        if ($alreadyWon) {
            Notification::make()
                ->danger()
                ->title('Conflict Detected')
                ->body("This customer has already won in this event while this preview was open.")
                ->send();
            $this->winner = null;
            return;
        }

        $ticket = LotteryTicket::find($ticketId);
        $participant = Participant::with('account.branch')->find($participantId);

        if (!$participant) {
            Notification::make()->danger()->title('Participant not found.')->send();
            return;
        }

        // Create Winner record
        Winner::create([
            'participant_id' => $participantId,
            'participant_cif' => $participant->participant_cif,
            'participant_account_number' => $participant->participant_account_number,
            'participant_name' => $participant->participant_name,
            'participant_email' => $participant->participant_email,
            'participant_phone_number' => $participant->participant_phone_number,
            'event_prize_id' => $this->eventPrize->id,
            'prize_name' => $this->eventPrize->prize->prize_name,
            'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? 'Common',
            'prize_total_quantity' => $this->eventPrize->total_quantity,
            'prize_value' => $this->eventPrize->prize->value,
            'prize_description' => $this->eventPrize->prize->description,
            'event_code' => $this->eventPrize->event->event_code,
            'event_name' => $this->eventPrize->event->event_name,
            'draw_session_id' => $winnerData['draw_session_id'] ?? $this->drawSessionId,
            'winning_number' => $winnerData['lucky_number'],
            'drawn_at' => now(),
            'drawn_by' => Auth::user()->name ?? 'System',
            'lottery_ticket_id' => $ticketId,
            'total_points' => $ticket->total_points ?? 0,
            'range_start' => $ticket->range_start ?? 'N/A',
            'range_end' => $ticket->range_end ?? 'N/A',
            'status' => Winner::STATUS_PENDING,
            'branch_id' => $participant->account->branch_id,
            'branch_code' => $participant->account->branch->branch_code,
            'branch_name' => $participant->account->branch->branch_name,
            'branch_company_book' => $participant->account->branch->company_book,
            'branch_region' => $participant->account->branch->region,
        ]);

        // Reduce remaining quantity
        $this->eventPrize->decrement('remaining_quantity');

        Notification::make()
            ->success()
            ->title('Winner Confirmed!')
            ->body("{$participant->participant_name} has been recorded as the winner.")
            ->send();

        $this->winner = null;
        $this->isPreview = false;
        $this->notifWinnerExists = false;
        $this->checkWinner();
    }
}
