<?php

namespace App\Filament\Pages;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Prize;
use App\Models\Setting;
use App\Models\Winner;
use Auth;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\WithoutScrolling;

#[WithoutScrolling]
class DrawWinnerBulk extends Page
{
    use InteractsWithForms, WithPagination;
    protected string $view = 'filament.pages.draw-winner-bulk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Gift;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'draw-winner-bulk/{eventPrize}';

    public ?int $batchId = null;
    public $isSingleDrawingMode = false;
    public ?string $batchStatus = null;
    public ?int $processedCount = 0;
    public ?int $totalToProcess = 0;

    public ?int $eventPrizeId = null;
    public ?EventPrize $eventPrize = null;

    public ?array $searchData = [];
    public $winners = [];
    public ?bool $alreadyConfirmed = false;

    public $notifWinnerExists = true;

    public $drawSessionId;
    public ?bool $enableRedraw = false;
    public bool $isPreview = false;

    public ?int $splitDraw = null;

    public ?int $totalWinners = 0;
    public ?int $remainingQuantity = 0;
    public bool $needConfirm = false;
    public ?float $processPercentage = 0;
    public bool $isStopping = false;
    public ?int $stopTriggeredAt = null;
    public $newWinners = [];

    #[Computed]
    public function paginatedWinners()
    {
        return Winner::where('event_prize_id', $this->eventPrizeId)->orderBy('id', 'desc')->paginate(30);
    }

    public function __construct()
    {
        $this->enableRedraw = (bool) Setting::where('key', 'activate_re_draw_and_confirm')->first()->value ?? true;
        $this->alreadyConfirmed = (bool) $this->enableRedraw == false ? true : false;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('export_csv')
                ->label(__('Export CSV'))
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn() => $this->exportCsv()),
            \Filament\Actions\Action::make('export_excel')
                ->label(__('Export Excel'))
                ->color('emerald')
                ->icon('heroicon-o-table-cells')
                ->action(fn() => $this->exportExcel()),
            \Filament\Actions\Action::make('back')
                ->label(__('Go Back'))
                ->url(route('filament.admin.resources.events.view', $this->eventPrize->event_id))
                ->color('gray')
                ->button()
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function exportCsv($extension = 'csv')
    {
        $winnersToExport = $this->newWinners;
        if (empty($winnersToExport)) {
            // If no new winners, fallback to results in winners array
            if ($this->winners) {
                $winnersToExport = collect($this->winners)->flatten(1)->toArray();
            } else {
                // Fallback to searching confirmed ones if none in memory
                $winnersToExport = Winner::where('event_prize_id', $this->eventPrizeId)
                    ->where('draw_session_id', $this->drawSessionId)
                    ->get()
                    ->toArray();
            }
        }

        if (empty($winnersToExport)) {
            Notification::make()->warning()->title('No winners to export')->send();
            return null;
        }

        $filename = "bulk_winners_" . now()->format('Ymd_His') . "." . $extension;

        if ($extension === 'xls') {
            return response()->streamDownload(function () use ($winnersToExport) {
                echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head><meta http-equiv="content-type" content="text/html; charset=utf-8"/></head>
                <body><table border="1">
                    <thead>
                        <tr style="background-color: #f3f4f6;">
                            <th>CIF</th>
                            <th>Account Number</th>
                            <th>Name</th>
                            <th>Branch</th>
                            <th>Region</th>
                            <th>Lucky Number</th>
                            <th>Points</th>
                            <th>Drawn At</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($winnersToExport as $w) {
                    echo '<tr>
                        <td>' . ($w['cif'] ?? ($w['participant']['participant_cif'] ?? 'N/A')) . '</td>
                        <td>' . ($w['account']['account_number'] ?? ($w['participant']['participant_account_number'] ?? 'N/A')) . '</td>
                        <td>' . ($w['name'] ?? ($w['participant']['participant_name'] ?? 'N/A')) . '</td>
                        <td>' . ($w['branch_name'] ?? ($w['account']['branch']['branch_name'] ?? 'N/A')) . '</td>
                        <td>' . ($w['region'] ?? ($w['account']['branch']['region'] ?? 'N/A')) . '</td>
                        <td>' . ($w['lucky_number'] ?? $w['winning_number'] ?? 'N/A') . '</td>
                        <td>' . ($w['ticket']['total_points'] ?? 0) . '</td>
                        <td>' . ($w['drawn_at'] ?? now()->format('Y-m-d H:i:s')) . '</td>
                    </tr>';
                }
                echo '</tbody></table></body></html>';
            }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
        }

        return response()->streamDownload(function () use ($winnersToExport) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['CIF', 'Account Number', 'Name', 'Branch', 'Region', 'Lucky Number', 'Points', 'Drawn At']);

            foreach ($winnersToExport as $w) {
                fputcsv($file, [
                    $w['cif'] ?? ($w['participant']['participant_cif'] ?? 'N/A'),
                    $w['account']['account_number'] ?? ($w['participant']['participant_account_number'] ?? 'N/A'),
                    $w['name'] ?? ($w['participant']['participant_name'] ?? 'N/A'),
                    $w['branch_name'] ?? ($w['account']['branch']['branch_name'] ?? 'N/A'),
                    $w['region'] ?? ($w['account']['branch']['region'] ?? 'N/A'),
                    $w['lucky_number'] ?? $w['winning_number'] ?? 'N/A',
                    $w['ticket']['total_points'] ?? 0,
                    $w['drawn_at'] ?? now()->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($file);
        }, $filename);
    }

    public function exportExcel()
    {
        return $this->exportCsv('xls');
    }

    public function mount($eventPrize)
    {
        $this->eventPrizeId = $eventPrize->id;
        if (!$this->eventPrizeId) {
            abort(404);
        }

        $this->eventPrize = EventPrize::with(['event', 'prize'])->findOrFail($this->eventPrizeId);
        $this->splitDraw = $this->eventPrize->split_draw;

        $this->checkDrawSession();

        $this->form->fill([
            'draw_session_id' => $this->drawSessionId
        ]);

        $this->remainingQuantity = $this->eventPrize?->remaining_quantity ?? 0;

        if ($this->checkWinner() && $this->notifWinnerExists) {
            Notification::make()
                ->danger()
                ->title('Winner Already Exists')
                ->body("Winner for this event prize already exists.")
                ->send();
        }
    }

    private function checkDrawSession()
    {
        $this->drawSessionId = DrawSession::where('event_id', $this->eventPrize->event_id)
            ->where('status', DrawSession::STATUS_ACTIVE)
            ->where('started_at', '<=', now())
            ->where('ended_at', '>=', now())->first()?->id;
    }

    public function updatedPage()
    {
        if (!$this->isPreview) {
            $this->checkWinner();
            $this->dispatch('scroll-to-results');
        }
    }

    private function checkWinner()
    {
        $this->isPreview = false;
        $paginatedWinners = $this->paginatedWinners;

        if ($paginatedWinners->count() > 0) {
            $this->totalWinners = $paginatedWinners->total();

            $this->winners = collect($paginatedWinners->items())->map(function ($winner) {
                return $winner->getDataBulk();
            })->split(2)->toArray();


            return true;
        }

        $this->remainingQuantity = $this->eventPrize->remaining_quantity;

        $this->winners = [];
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $splitDraw = (fn() => $this->eventPrize->split_draw)();
        $this->searchData['split_draw'] = $splitDraw;

        $sessions = DrawSession::where('event_id', $this->eventPrize->event_id)->get();

        $splitDrawValue = min($this->eventPrize?->remaining_quantity, $this->eventPrize?->split_draw > 0 ? $this->eventPrize->split_draw : 10);

        return $schema
            ->components([
                Select::make('draw_session_id')
                    ->label('Draw Session')
                    ->options($sessions->pluck('name', 'id'))
                    ->required()
                    ->searchable()
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
                    ->disabled(fn(): bool => ($this->winners != null && count($this->winners) == $this->eventPrize->remaining_quantity)),
                Hidden::make('draw_session_id')
                    ->default(fn() => $this->drawSessionId)
                    ->disabled(fn(): bool => ($this->winners != null && count($this->winners) == $this->eventPrize->remaining_quantity)),
                TextInput::make('split_draw')
                    ->label('Split Draw')
                    ->numeric()
                    ->helperText('Number of winners to generate in this batch.')
                    ->required()
                    ->minValue(1)
                    ->maxValue(fn() => $this->eventPrize->remaining_quantity)
                    ->default($splitDrawValue)
                    ->disabled(fn(): bool => (($this->winners != null || count($this->winners) > 0) && count($this->winners) == $this->eventPrize->remaining_quantity)),
            ])
            ->statePath('searchData');
    }

    public function draw()
    {
        $data = $this->form->getState();
        $this->eventPrize->refresh();
        $remainingQuantity = $this->eventPrize->remaining_quantity;

        if ($remainingQuantity <= 0) {
            Notification::make()->danger()->title('No items left')->send();
            return;
        }

        if ($data['split_draw'] > $remainingQuantity) {
            Notification::make()->danger()->title('Split draw is greater than remaining quantity')->send();
            return;
        }

        $totalWinners = $data['split_draw'] > 0 ? $data['split_draw'] : $remainingQuantity;

        // Create a batch record
        $batch = \App\Models\BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $data['draw_session_id'],
            'total_winners' => $totalWinners,
            'status' => 'PENDING',
            'created_by' => Auth::user()->name,
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $totalWinners;
        $this->processedCount = 0;

        // Dispatch background job
        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);

        if ($this->enableRedraw) {
            $this->alreadyConfirmed = false;
            $this->isPreview = true;
        }

        Notification::make()
            ->info()
            ->title('Drawing Started')
            ->body("System is generating {$totalWinners} of {$remainingQuantity} winners in the background. Please wait...")
            ->send();
    }

    public function stopDrawing()
    {
        if (!$this->batchId)
            return;

        $batch = \App\Models\BulkDrawBatch::find($this->batchId);
        if ($batch && in_array($batch->status, ['PENDING', 'PROCESSING', 'COMPLETED'])) {
            if ($batch->status !== 'COMPLETED') {
                $batch->update(['status' => 'CANCELLED']);
                $this->batchStatus = 'CANCELLED';
            }

            $this->isStopping = true;
            $this->stopTriggeredAt = time();

            Notification::make()
                ->warning()
                ->title('Stopping drawing process')
                ->body("Drawing will finish in the background if not already done. Results will be shown shortly.")
                ->send();
        }
    }

    public function checkBatchStatus()
    {
        if (!$this->batchId)
            return;

        $batch = \App\Models\BulkDrawBatch::find($this->batchId);
        if (!$batch)
            return;

        $this->totalToProcess = $batch->total_to_process;
        $this->processedCount = $batch->processed_winners;

        if ($batch->total_to_process == 1) {
            $this->isSingleDrawingMode = true;
        }

        // Progressive loading: Update table while processing
        if (!$this->isStopping && $batch->results && count($batch->results) > 0) {
            $this->isPreview = true;
            $this->totalWinners = count($batch->results);

            // Merge with existing winners for perspective
            $existingWinners = Winner::where('event_prize_id', $this->eventPrizeId)
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->map(fn(Winner $w) => $w->getDataBulk())
                ->toArray();

            $this->winners = collect(array_merge($batch->results, $existingWinners))->split(2)->toArray();
        }

        $this->processPercentage = $this->totalToProcess > 0 ? ($this->processedCount * 100 / $this->totalToProcess) : 0;

        if ($batch->status === 'COMPLETED' || $batch->status === 'CANCELLED') {

            // If we are in stopping sequence, wait for 100% completion AND the delay?
            // Actually, for admin, we just wait for the user to be ready (handled by logic below)
            if ($this->isStopping || $this->stopTriggeredAt) {
                $drawDelay = Setting::where('key', 'draw_delay')->first()->value ?? 3;
                $timeRemaining = $drawDelay - (time() - $this->stopTriggeredAt);
                $isWorkDone = ($this->processedCount >= $this->totalToProcess);

                if ($timeRemaining > 0 || !$isWorkDone) {
                    return; // Keep polling
                }
            }

            $this->batchId = null; // Stop polling
            $this->isStopping = false; // Reset stopping state
            $this->newWinners = $batch->results ?? [];

            $batch->update(['status' => 'COMPLETED']);
            $this->batchStatus = 'COMPLETED';

            if (!$this->enableRedraw) {
                $this->confirmWinner();
                $this->notifWinnerExists = false;
                $this->checkWinner();
            } else {
                $this->isPreview = true;
                $this->totalWinners = count($this->newWinners);

                // Show all new winners with some oldest winners
                $existingWinners = Winner::where('event_prize_id', $this->eventPrizeId)
                    ->orderBy('id', 'desc')
                    ->limit(20)
                    ->get()
                    ->map(fn(Winner $w) => $w->getDataBulk())
                    ->toArray();

                $combined = array_merge($this->newWinners, $existingWinners);
                $this->winners = collect($combined)->split(2)->toArray();

                $title = $batch->status === 'CANCELLED' ? 'Winners Generated (Stopped)' : 'Winners Generated';
                $body = count($this->newWinners) . " winners are ready for confirmation.";

                Notification::make()
                    ->success()
                    ->title($title)
                    ->body($body)
                    ->send();
            }
        } elseif ($batch->status === 'FAILED') {
            $this->batchId = null; // Stop polling
            Notification::make()
                ->danger()
                ->title('Drawing Failed')
                ->body($batch->error_message)
                ->send();
        }
    }

    public function clearWinner()
    {
        $this->winners = null;
        $this->batchId = null;
        $this->batchStatus = null;
        $this->newWinners = [];
    }

    public function confirmWinner()
    {
        $toConfirm = !empty($this->newWinners) ? $this->newWinners : collect($this->winners)->flatten(1)->filter(fn($w) => !isset($w['id']))->toArray();

        if (empty($toConfirm))
            return;

        // Re-check availability
        $this->eventPrize->refresh();
        $count = count($toConfirm);

        if ($this->eventPrize->remaining_quantity < $count) {
            Notification::make()
                ->danger()
                ->title('Prize Quantity Mismatch')
                ->body("Sorry, the remaining quantity ({$this->eventPrize->remaining_quantity}) is less than the number of winners picked ({$count}). Please re-draw.")
                ->send();
            return;
        }


        \DB::beginTransaction();
        try {
            foreach ($toConfirm as $winnerData) {
                // Re-check customer eligibility one last time
                $customer_id = $winnerData['customer']['id'];
                $alreadyWon = Winner::whereHas('eventPrize', fn($q) => $q->where('event_prizes.event_id', $this->eventPrize->event_id))
                    ->whereHas('participant.account', fn($q) => $q->where('customer_id', $customer_id))
                    ->exists();

                if ($alreadyWon) {
                    throw new \Exception("Customer " . $winnerData['name'] . " has already won in this event.");
                }

                $ticket = $winnerData['ticket'];
                $participant = $winnerData['participant'];

                // Create Winner record
                Winner::create([
                    'participant_id' => $participant['id'],
                    'participant_cif' => $participant['participant_cif'],
                    'participant_account_number' => $participant['participant_account_number'] ?? ($winnerData['account']['account_number'] ?? 'N/A'),
                    'participant_name' => $participant['participant_name'],
                    'participant_email' => $participant['participant_email'] ?? 'N/A',
                    'participant_phone_number' => $participant['participant_phone_number'] ?? 'N/A',
                    'event_prize_id' => $this->eventPrize->id,
                    'prize_name' => $this->eventPrize->prize->prize_name,
                    'prize_tier' => Prize::PRIZE_TIER[$this->eventPrize->prize->tier] ?? Prize::TIER_COMMON,
                    'prize_total_quantity' => $this->eventPrize->total_quantity,
                    'prize_value' => $this->eventPrize->prize->value,
                    'prize_description' => $this->eventPrize->prize->description,
                    'event_code' => $this->eventPrize->event->event_code,
                    'event_name' => $this->eventPrize->event->event_name,
                    'draw_session_id' => $winnerData['draw_session_id'] ?? $this->searchData['draw_session_id'] ?? $this->drawSessionId,
                    'winning_number' => $winnerData['lucky_number'],
                    'drawn_at' => now(),
                    'drawn_by' => Auth::user()->name ?? 'System',
                    'lottery_ticket_id' => $ticket['id'],
                    'total_points' => $ticket['total_points'],
                    'range_start' => $ticket['range_start'],
                    'range_end' => $ticket['range_end'],
                    'status' => Winner::STATUS_PENDING,
                    'branch_id' => $winnerData['account']['branch']['id'],
                    'branch_code' => $winnerData['account']['branch']['code'],
                    'branch_name' => $winnerData['account']['branch']['branch_name'],
                    'branch_company_book' => $winnerData['account']['branch']['company_book'],
                    'branch_region' => $winnerData['account']['branch']['region'],
                ]);

                // Reduce remaining quantity
                $this->eventPrize->decrement('remaining_quantity');
            }

            \DB::commit();

            Notification::make()
                ->success()
                ->title('Winners Confirmed!')
                ->body($count . " winners have been recorded.")
                ->send();

            $this->winners = null;
            $this->newWinners = [];
            $this->isPreview = false;
            $this->alreadyConfirmed = true;
            $this->checkWinner();
        } catch (\Exception $e) {
            \DB::rollBack();
            Notification::make()
                ->danger()
                ->title('Conflict Detected')
                ->body($e->getMessage())
                ->send();
            $this->winners = null;
            $this->newWinners = [];
        }
    }
}
