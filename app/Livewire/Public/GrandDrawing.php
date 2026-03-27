<?php

namespace App\Livewire\Public;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\Prize;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\BulkDrawBatch;
use App\Models\Participant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\WithoutScrolling;

#[WithoutScrolling]
class GrandDrawing extends Component
{
    use WithPagination;
    public $uuid;
    public $eventPrize;
    public $drawSessionId;
    public $winners = [];
    public $isDrawing = false;
    public $pendingWinner = null;
    public bool $enableRedraw = false;
    public bool $isPreview = false;
    public $winner = null;
    public $candidates = [];

    #[Computed]
    public function paginatedWinners()
    {
        return Winner::where('event_prize_id', $this->eventPrize->id)->orderBy('id', 'desc')->paginate(30);
    }

    public function mount($uuid)
    {
        $this->uuid = $uuid;
        $this->eventPrize = EventPrize::with(['event', 'prize'])->where('uuid', $uuid)->firstOrFail();

        // Auto-select active session if exists
        $this->drawSessionId = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', DrawSession::STATUS_ACTIVE)
            ->where('started_at', '<=', now())
            ->where('ended_at', '>=', now())
            ->first()?->id;

        $this->enableRedraw = (bool) Setting::where('key', 'activate_re_draw_and_confirm')->first()->value ?? true;

        $this->checkWinner();
    }

    public function updatedPage()
    {
        if (!$this->isPreview) {
            $this->checkWinner();
        }
    }

    private function checkWinner()
    {
        $this->isPreview = false;
        $paginatedWinners = $this->paginatedWinners();

        if ($paginatedWinners->count() > 0) {
            $firstWinner = $paginatedWinners->first();
            $firstWinner->load(['participant.account.branch', 'participant.account.customer']);

            $this->winner = [
                'id' => $firstWinner->id,
                'ticket' => $firstWinner->lotteryTicket?->toArray() ?? [],
                'participant' => $firstWinner->participant?->toArray() ?? [],
                'customer' => $firstWinner->participant?->account?->customer?->toArray() ?? [],
                'lucky_number' => $firstWinner->winning_number,
                'winning_number' => $firstWinner->range_start === $firstWinner->range_end
                    ? $firstWinner->range_start
                    : "{$firstWinner->range_start} - {$firstWinner->range_end}",
                'draw_session_id' => $firstWinner->draw_session_id,
                'branch_name' => $firstWinner->branch_name,
                'region' => $firstWinner->branch_region,
                'drawn_at' => $firstWinner->created_at->format('Y-m-d H:i:s'),
            ];

            // For the table display
            $this->winners = collect($paginatedWinners->items())->map(fn(Winner $w) => $w->getDataBulk())->split(2)->toArray();

            return true;
        }

        $this->winners = [];
        $this->winner = null;
        return false;
    }

    public function startDrawing()
    {
        $this->eventPrize->refresh();
        if ($this->eventPrize->remaining_quantity <= 0) {
            $this->dispatch('error', message: 'Prize exhausted.');
            return;
        }

        if (!$this->drawSessionId) {
            $this->dispatch('error', message: 'No active draw session selected.');
            return;
        }

        $this->isDrawing = true;
        $this->dispatch('trigger-animation');
    }

    public function performDraw()
    {
        // Check if prize is still available right before finding a winner
        $this->eventPrize->refresh();
        if ($this->eventPrize->remaining_quantity <= 0) {
            $this->isDrawing = false;
            $this->dispatch('error', message: 'Prize exhausted.');
            return;
        }

        $eventId = $this->eventPrize->event_id;

        // Fetch weights from Settings
        $weightsSetting = Setting::where('key', 'region_weights')->first();
        $weights = $weightsSetting ? json_decode($weightsSetting->value, true) : [
            'Jawa' => 50,
            'Sumatera' => 20,
            'Sulawesi' => 20,
            'Lainnya' => 10,
        ];

        // Algorithm to pick target region
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

        // 1. Pick the real winner ticket (Weighted by total_points)
        $winnerTicket = $this->findWinnerInRegion($targetRegion, $eventId);

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

        if (!$winnerTicket) {
            $winnerTicket = $this->findWinnerInRegion(null, $eventId);
        }

        if (!$winnerTicket) {
            $this->isDrawing = false;
            $this->dispatch('error', message: 'No eligible winner found.');
            return;
        }

        // 2. Fetch real candidate lucky numbers for the animation
        $candidateTickets = LotteryTicket::query()
            ->where('event_id', $eventId)
            ->where('total_points', '>=', $this->eventPrize->min_points_required)
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->inRandomOrder()
            ->limit(20)
            ->get();

        $this->candidates = $candidateTickets->map(fn($t) => $this->generateLuckyNumber($t))->toArray();

        // 3. Set winner and specific lucky number
        $winnerTicket->load(['participant.account.branch', 'participant.account.customer']);
        $luckyNumber = $this->generateLuckyNumber($winnerTicket);

        $this->isPreview = true;
        $this->pendingWinner = [
            'ticket' => $winnerTicket->toArray(),
            'participant' => $winnerTicket->participant->toArray(),
            'customer' => $winnerTicket->participant->account->customer->toArray(),
            'lucky_number' => $luckyNumber, // The specific 9-digit winner
            'winning_number' => $winnerTicket->range_start === $winnerTicket->range_end
                ? $winnerTicket->range_start
                : "{$winnerTicket->range_start} - {$winnerTicket->range_end}",
            'draw_session_id' => $this->drawSessionId,
        ];

        // Keep isDrawing = true until finishDrawing is called after animation
        $this->winner = null;
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
            $this->checkWinner();
        }
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
            ->where('status', LotteryTicket::STATUS_ACTIVE)
            ->whereHas('participant.account.customer', function ($q) use ($eventId) {
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

        // To be fair, select weighted by the size of their range (total_points)
        $tickets = $query->get();
        if ($tickets->isEmpty())
            return null;

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

    public function confirmWinner()
    {
        if (!$this->winner)
            return;

        // Re-check
        $this->eventPrize->refresh();
        if ($this->eventPrize->remaining_quantity <= 0) {
            $this->dispatch('error', message: 'Prize exhausted.');
            $this->winner = null;
            return;
        }

        // Handle array or object access
        $winnerData = $this->winner;
        $ticketId = $winnerData['ticket']['id'] ?? null;
        $participantId = $winnerData['participant']['id'] ?? null;
        $customerId = $winnerData['customer']['id'] ?? null;

        if (!$participantId) {
            $this->dispatch('error', message: "Invalid participant data.");
            return;
        }

        // Re-check customer eligibility one last time
        $alreadyWon = Winner::whereHas('eventPrize', fn($q) => $q->where('event_prizes.event_id', $this->eventPrize->event_id))
            ->whereHas('participant.account', fn($q) => $q->where('customer_id', $customerId))
            ->exists();

        if ($alreadyWon) {
            $this->dispatch('error', message: "This customer has already won in this event.");
            $this->winner = null;
            return;
        }

        $ticket = LotteryTicket::find($ticketId);
        $participant = Participant::with('account.branch')->find($participantId);

        if (!$participant) {
            $this->dispatch('error', message: "Participant not found.");
            return;
        }

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
            'draw_session_id' => $this->drawSessionId,
            'winning_number' => $winnerData['lucky_number'],
            'drawn_at' => now(),
            'drawn_by' => Auth::user()->name ?? 'Guest User',
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

        $this->eventPrize->decrement('remaining_quantity');

        $this->dispatch('success', message: 'Winner confirmed and saved successfully!');
        $this->dispatch('winner-confirmed');
        $this->winner = null;
        $this->isPreview = false;
        $this->checkWinner();
    }

    #[Layout('layouts.guest')]
    public function render()
    {
        return view('livewire.public.grand-drawing');
    }
}
