<?php

namespace App\Filament\Pages;

use App\Models\DrawSession;
use App\Models\EventPrize;
use App\Models\LotteryTicket;
use App\Models\Prize;
use App\Models\Setting;
use App\Models\Winner;
use App\Models\TemporaryWinner;
use App\Models\BulkDrawBatch;
use Auth;
use BackedEnum;
use DB;
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
        $winnersToExport = $this->winners ? collect($this->winners)->flatten(1)->toArray() : [];
        
        if (empty($winnersToExport)) {
            // Fallback to confirmed ones
            $winnersToExport = Winner::where('event_prize_id', $this->eventPrizeId)
                ->where('draw_session_id', $this->drawSessionId)
                ->get()
                ->map(fn($w) => $w->getDataBulk())
                ->toArray();
        }

        if (empty($winnersToExport)) {
            Notification::make()->warning()->title('No winners to export')->send();
            return null;
        }

        $filename = "bulk_winners_" . (isset($this->eventPrize) ? str_replace([' ', '/', '\\'], '_', $this->eventPrize->prize->prize_name) : 'export') . "_" . now()->format('Ymd_His') . "." . $extension;

        if ($extension === 'xls') {
            return response()->streamDownload(function () use ($winnersToExport) {
                echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head><meta http-equiv="content-type" content="text/html; charset=utf-8"/></head>
                <body><table border="1">
                    <thead>
                        <tr style="background-color: #f3f4f6;">
                            <th>CIF</th><th>Account Number</th><th>Name</th><th>Branch</th><th>Region</th><th>Lucky Number</th><th>Points</th><th>Drawn At</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($winnersToExport as $w) {
                    echo '<tr>
                        <td>' . ($w['cif'] ?? 'N/A') . '</td>
                        <td>' . ($w['account']['account_number'] ?? $w['account_number'] ?? 'N/A') . '</td>
                        <td>' . ($w['name'] ?? 'N/A') . '</td>
                        <td>' . ($w['branch_name'] ?? 'N/A') . '</td>
                        <td>' . ($w['region'] ?? 'N/A') . '</td>
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
                    $w['cif'] ?? 'N/A',
                    $w['account']['account_number'] ?? $w['account_number'] ?? 'N/A',
                    $w['name'] ?? 'N/A',
                    $w['branch_name'] ?? 'N/A',
                    $w['region'] ?? 'N/A',
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
        $this->eventPrize = EventPrize::with(['event', 'prize'])->findOrFail($this->eventPrizeId);
        $this->splitDraw = $this->eventPrize->split_draw;

        $this->checkDrawSession();

        $this->form->fill([
            'draw_session_id' => $this->drawSessionId
        ]);

        $this->remainingQuantity = $this->eventPrize->remaining_quantity;

        if ($this->checkWinner() && $this->notifWinnerExists && !$this->isPreview) {
            Notification::make()
                ->danger()
                ->title('Winner Already Exists')
                ->body("Confirmed winners for this event prize already exist.")
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
        
        // Stage 1: Check Temporary Winners for preview
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrizeId)
            ->get();

        if ($tempWinners->count() > 0) {
            $this->newWinners = $tempWinners->map(fn($tw) => $tw->getData())->toArray();
            $this->isPreview = true;
            $this->totalWinners = count($this->newWinners);
            $this->winners = collect($this->newWinners)->split(2)->toArray();
            return true;
        }

        // Stage 2: Check Confirmed Winners
        $paginatedWinners = $this->paginatedWinners;
        if ($paginatedWinners->count() > 0) {
            $this->totalWinners = $paginatedWinners->total();
            $this->winners = collect($paginatedWinners->items())->map(fn($w) => $w->getDataBulk())->split(2)->toArray();
            return true;
        }

        $this->remainingQuantity = $this->eventPrize->remaining_quantity;
        $this->winners = [];
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $splitDrawValue = min($this->eventPrize?->remaining_quantity, $this->eventPrize?->split_draw > 0 ? $this->eventPrize->split_draw : 10);
        $sessions = DrawSession::where('event_id', $this->eventPrize->event_id)->get();

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
                        if (!$session) return true;
                        $now = now();
                        return $session->status !== DrawSession::STATUS_ACTIVE || $now->lt($session->started_at) || $now->gt($session->ended_at);
                    }),
                TextInput::make('split_draw')
                    ->label('Split Draw')
                    ->numeric()
                    ->helperText('Number of winners to generate in this batch.')
                    ->required()
                    ->minValue(1)
                    ->maxValue(fn() => $this->eventPrize->remaining_quantity)
                    ->default($splitDrawValue),
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
        
        // Reset temporary winners for this session/prize before a new draw
        TemporaryWinner::where('draw_session_id', $data['draw_session_id'])
            ->where('event_prize_id', $this->eventPrizeId)
            ->delete();

        $totalWinners = $data['split_draw'] > 0 ? $data['split_draw'] : $remainingQuantity;

        // Create a batch record
        $batch = BulkDrawBatch::create([
            'event_prize_id' => $this->eventPrize->id,
            'draw_session_id' => $data['draw_session_id'],
            'total_winners' => $totalWinners,
            'status' => 'PENDING',
            'created_by' => Auth::user()->name,
        ]);

        $this->batchId = $batch->id;
        $this->batchStatus = 'PENDING';
        $this->totalToProcess = $totalWinners;
        $this->newWinners = [];
        $this->isStopping = false;

        \App\Jobs\ProcessBulkDrawJob::dispatch($batch->id);

        Notification::make()
            ->info()
            ->title('Drawing Started')
            ->body("Generating {$totalWinners} winners. Results will appear in the staging area.")
            ->send();
            
        $this->isPreview = true;
    }

    public function stopDrawing()
    {
        if (!$this->batchId) return;

        $batch = BulkDrawBatch::find($this->batchId);
        if ($batch && in_array($batch->status, ['PENDING', 'PROCESSING', 'COMPLETED'])) {
            if ($batch->status !== 'COMPLETED') {
                $batch->update(['status' => 'CANCELLED']);
            }
            $this->isStopping = true;
            $this->stopTriggeredAt = time();
            Notification::make()->warning()->title('Stopping drawing process')->send();
        }
    }

    public function checkBatchStatus()
    {
        if (!$this->batchId) return;

        $batch = BulkDrawBatch::find($this->batchId);
        if (!$batch) return;

        $this->processedCount = $batch->processed_winners;
        $this->processPercentage = $this->totalToProcess > 0 ? ($this->processedCount * 100 / $this->totalToProcess) : 0;

        if (in_array($batch->status, ['COMPLETED', 'CANCELLED', 'FAILED'])) {
            if ($this->isStopping || $this->stopTriggeredAt) {
                $drawDelay = Setting::where('key', 'draw_delay')->first()->value ?? 3;
                if ((time() - $this->stopTriggeredAt) < $drawDelay) return;
            }

            $this->batchId = null;
            $this->isStopping = false;
            $this->checkWinner(); // This will load temporary winners into UI

            if ($batch->status !== 'FAILED') {
                 Notification::make()->success()->title('Winners Generated')->body("Review the staged winners before confirming.")->send();
            } else {
                 Notification::make()->danger()->title('Drawing Failed')->body($batch->error_message)->send();
            }
        }
    }

    public function resetWinners()
    {
        TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrizeId)
            ->delete();

        $this->isPreview = false;
        $this->newWinners = [];
        $this->checkWinner();
        Notification::make()->info()->title('Staging Cleared')->send();
    }

    public function confirmWinner()
    {
        $tempWinners = TemporaryWinner::where('draw_session_id', $this->drawSessionId)
            ->where('event_prize_id', $this->eventPrizeId)
            ->get();

        if ($tempWinners->isEmpty()) return;

        DB::beginTransaction();
        try {
            foreach ($tempWinners as $tw) {
                $this->eventPrize->refresh();
                if ($this->eventPrize->remaining_quantity <= 0) throw new \Exception("Prize quantity exhausted.");

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
                    'drawn_by' => Auth::user()->name ?? 'System',
                ]);

                Winner::create($fullData);
                $this->eventPrize->decrement('remaining_quantity');
                $tw->delete();
            }
            DB::commit();

            Notification::make()->success()->title('Winners Confirmed!')->send();
            $this->isPreview = false;
            $this->checkWinner();
        } catch (\Exception $e) {
            DB::rollBack();
            Notification::make()->danger()->title('Confirmation Failed')->body($e->getMessage())->send();
        }
    }

    public function clearWinner()
    {
        $this->resetWinners();
    }
}
