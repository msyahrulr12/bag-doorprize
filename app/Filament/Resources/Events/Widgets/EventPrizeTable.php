<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\EventPrize;
use App\Models\Prize;
use App\Models\TemporaryWinner;
use App\Models\DrawSession;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;


class EventPrizeTable extends TableWidget
{
    public ?Model $record = null;
    public ?int $event_id = null;
    public ?string $activeTab = null;
    public ?int $selectedDrawSession = null;

    public function setActiveTab(?int $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    public function getBaseQuery(): Builder
    {
        $query = EventPrize::query();

        if ($this->record) {
            $query->where('event_id', $this->record->id);
        } elseif ($this->event_id) {
            $query->where('event_id', $this->event_id);
        }

        return $query;
    }

    public function getTabs()
    {
        $drawSessions = $this->getDrawSessions();

        return $drawSessions;
    }

    public function getDrawSessions()
    {
        return DrawSession::where('event_id', $this->record->id)->orderBy('started_at', 'asc')->get();
    }

    public function parseTab(?int $drawSessionId): string
    {
        return $drawSessionId ? $this->getTabs()->filter(fn($drawSession) => (int) $drawSession->id === $drawSessionId)->first()->name : 'All';
    }

    public function table(Table $table): Table
    {
        $tabs = $this->getTabs();

        return $table->query(function (): Builder {
            $query = $this->getBaseQuery();
            if ($this->activeTab) {
                $query->where('draw_session_id', $this->activeTab);
            }
            return $query;
        })
            ->columns([
                TextColumn::make('drawSession.name')
                    ->label('Draw Session')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('prize.prize_code')
                    ->label('Prize Code')
                    ->searchable(),
                ImageColumn::make('prize.prize_image')
                    ->label('Image')
                    ->circular(),
                TextColumn::make('prize.prize_name')
                    ->label('Prize Name')
                    ->searchable(),
                TextColumn::make('prize.tier')
                    ->label('Prize Tier')
                    ->state(function ($record): string {
                        return Prize::PRIZE_TIER[$record->prize->tier ?? count(Prize::PRIZE_TIER) - 1];
                    })
                    ->searchable(),
                TextColumn::make('prize.value')
                    ->label('Prize Value')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('drawSession.name')
                    ->label('Draw Session')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('remaining_quantity')
                    ->label('Remaining Qty')
                    ->state(function (EventPrize $record): int {
                        $staged = TemporaryWinner::where('event_prize_id', $record->id)
                            ->count();
                        return max(0, $record->remaining_quantity - $staged);
                    })
                    ->numeric()
                    ->sortable(),
                TextColumn::make('min_points_required')
                    ->label('Min Points Req.')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('total_value')
                    ->label('Total Value')
                    ->state(fn($record) => $record->prize->value * $record->total_quantity)
                    ->numeric(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('prize.value', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                ActionGroup::make([
                    Action::make('All Tabs')
                        ->label('All')
                        ->icon('heroicon-o-presentation-chart-line')
                        ->color(fn() => $this->activeTab === null ? 'primary' : 'gray')
                        ->action(fn() => $this->setActiveTab(null)),

                    ...$tabs->map(
                        fn($drawSession) => Action::make('tab_' . $drawSession->id)
                            ->label($drawSession->name)
                            ->icon("heroicon-o-presentation-chart-line")
                            ->color(fn() => (int) $this->activeTab === (int) $drawSession->id ? ($drawSession->status == DrawSession::STATUS_INACTIVE ? 'danger' : 'success') : 'gray')
                            ->action(fn() => $this->setActiveTab($drawSession->id))
                    ),
                ])
                    ->label("Choose Draw Session: " . $this->parseTab($this->activeTab))
                    ->icon("heroicon-o-calendar-days")
                    ->color('primary')
                    ->button(),
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\EventPrizeExporter::class)
                    ->label('Export CSV/Excel'),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        $query = EventPrize::query()->with('prize');
                        if ($this->record) {
                            $query->where('event_id', $this->record->id);
                        } elseif ($this->event_id) {
                            $query->where('event_id', $this->event_id);
                        }
                        $records = $query->get();
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.event-prizes', ['records' => $records]);
                        return response()->streamDownload(fn() => print($pdf->output()), 'event-prizes.pdf');
                    }),
                Action::make('export_winners_excel')
                    ->label('Export Winners Excel')
                    ->color('success')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Select::make('draw_session_id')
                            ->label('Draw Session')
                            ->options(function () {
                                $eventId = $this->record?->id ?? $this->event_id;
                                return DrawSession::where('event_id', $eventId)
                                    ->orderBy('started_at', 'asc')
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(function (array $data) {
                        $eventId = $this->record?->id ?? $this->event_id;
                        $drawSessionId = $data['draw_session_id'];

                        $drawSession = DrawSession::find($drawSessionId);
                        $event = $this->record ?? \App\Models\Event::find($eventId);

                        // Get all event prizes for this draw session, ordered by prize value desc
                        $eventPrizes = EventPrize::with('prize')
                            ->where('event_id', $eventId)
                            ->where('draw_session_id', $drawSessionId)
                            ->get()
                            ->sortBy(function ($ep) {
                                return $ep->prize->value ?? 0;
                            });

                        // Get all winners for this draw session, grouped by event_prize_id
                        $winners = \App\Models\Winner::with(['participant.account.branch', 'participant.account.customer'])
                            ->where('draw_session_id', $drawSessionId)
                            ->whereIn('event_prize_id', $eventPrizes->pluck('id'))
                            ->orderBy('id', 'asc')
                            ->get()
                            ->groupBy('event_prize_id');

                        $eventName = $event->event_name ?? 'Event';
                        $sessionName = $drawSession->name ?? 'Draw Session';
                        $title = "PEMENANG " . strtoupper($eventName) . " - " . strtoupper($sessionName);
                        $filename = 'winners_' . str_replace([' ', '/', '\\'], '_', $eventName) . '_' . str_replace([' ', '/', '\\'], '_', $sessionName) . '_' . now()->format('Ymd_His') . '.xls';

                        return response()->streamDownload(function () use ($eventPrizes, $winners, $title) {
                            $e = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                            echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
                            echo '<head><meta http-equiv="content-type" content="text/html; charset=utf-8"/>';
                            echo '<style>
                                td, th { font-family: Arial; font-size: 10pt; border: 1px solid #000; padding: 4px; }
                                .title { font-size: 14pt; font-weight: bold; text-align: center; border: none; }
                                .prize-header { font-size: 12pt; font-weight: bold; text-align: center; border: none; }
                                .col-header { font-weight: bold; background-color: #f3f4f6; text-align: center; }
                            </style></head><body>';

                            // Title row
                            echo '<table border="1">';
                            echo '<tr><td class="title" colspan="10">' . $e($title) . '</td></tr>';

                            $globalRow = 0;

                            foreach ($eventPrizes as $eventPrize) {
                                $prizeName = $eventPrize->prize->prize_name ?? 'Unknown Prize';
                                $prizeWinners = $winners->get($eventPrize->id, collect());

                                // Empty row separator
                                echo '<tr><td colspan="10" style="border:none;">&nbsp;</td></tr>';

                                // Prize name header
                                echo '<tr><td class="prize-header" colspan="10">' . $e($prizeName) . '</td></tr>';

                                // Column headers
                                echo '<tr>';
                                echo '<td class="col-header">TOTAL PEMENANG</td>';
                                echo '<td class="col-header">NO</td>';
                                echo '<td class="col-header">Name</td>';
                                echo '<td class="col-header">No Rekening</td>';
                                echo '<td class="col-header">Branch</td>';
                                echo '<td class="col-header">Wilayah</td>';
                                echo '<td class="col-header">Prize Name</td>';
                                echo '<td class="col-header">Lucky Number</td>';
                                echo '<td class="col-header">No. KTP</td>';
                                echo '<td class="col-header">NPWP</td>';
                                echo '</tr>';

                                if ($prizeWinners->isEmpty()) {
                                    echo '<tr><td colspan="10" style="text-align:center;">No winners</td></tr>';
                                } else {
                                    $no = 0;
                                    foreach ($prizeWinners as $winner) {
                                        $globalRow++;
                                        $no++;

                                        $name = $winner->participant_name ?? ($winner->participant?->account?->customer?->name ?? 'N/A');
                                        $accountNumber = $winner->participant_account_number ?? ($winner->participant?->account?->account_number ?? 'N/A');
                                        $branch = $winner->branch_name ?? ($winner->participant?->account?->branch?->branch_name ?? 'N/A');
                                        $region = $winner->branch_region ?? ($winner->participant?->account?->branch?->region ?? 'N/A');
                                        $luckyNumber = $winner->winning_number ?? 'N/A';

                                        echo '<tr>';
                                        echo '<td>' . $e((string) $globalRow) . '</td>';
                                        echo '<td>' . $e((string) $no) . '</td>';
                                        echo '<td>' . $e($name) . '</td>';
                                        echo '<td>' . $e($accountNumber) . '</td>';
                                        echo '<td>' . $e($branch) . '</td>';
                                        echo '<td>' . $e($region) . '</td>';
                                        echo '<td>' . $e($winner->prize_name ?? $prizeName) . '</td>';
                                        echo '<td>' . $e($luckyNumber) . '</td>';
                                        echo '<td>N/A</td>';
                                        echo '<td>N/A</td>';
                                        echo '</tr>';
                                    }
                                }
                            }

                            echo '</table></body></html>';
                        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
                    }),
                CreateAction::make()
                    ->form([
                        Hidden::make('event_id')
                            ->default(fn() => $this->record?->id),
                        Select::make('draw_session_id')
                            ->relationship(
                                'drawSession',
                                'name',
                                fn(Builder $query) => $query
                                    ->where('event_id', $this->record?->id ?? $this->event_id)
                                    ->orderBy('started_at', 'asc')
                            )
                            ->afterStateUpdated(fn(Set $set) => $set('prize_id', null))
                            ->required()
                            ->searchable()
                            ->live()
                            ->preload(),
                        Select::make('prize_id')
                            ->relationship(
                                'prize',
                                'prize_name',
                                modifyQueryUsing: fn(Builder $query, Get $get) => $query->whereNotIn('id', EventPrize::where('draw_session_id', $get('draw_session_id'))->pluck('prize_id')->toArray())
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn(Get $get) => !$get('draw_session_id'))
                            ->placeholder(fn(Get $get) => $get('draw_session_id') ? 'Select Prize' : 'Select an draw session first'),
                        TextInput::make('total_quantity')
                            ->numeric()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn($state, $set) => [
                                $set('remaining_quantity', $state),
                                $set('split_draw', $state)
                            ]),
                        TextInput::make('remaining_quantity')
                            ->numeric()
                            ->required(),
                        TextInput::make('min_points_required')
                            ->numeric()
                            ->required(),
                        TextInput::make('split_draw')
                            ->numeric()
                            ->required(),
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Admin Draw')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->url(fn(EventPrize $record): string => in_array($record->prize->tier, [Prize::TIER_GRAND_PRIZE]) ? route('filament.admin.pages.draw-winner.{eventPrize}', ['eventPrize' => $record->id]) : route('filament.admin.pages.draw-winner-bulk.{eventPrize}', ['eventPrize' => $record->id]))
                    ->visible(false),
                ViewAction::make('public_draw')
                    ->label('Public Draw')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    // ->url(fn(EventPrize $record): string => in_array($record->prize->tier, [Prize::TIER_GRAND_PRIZE]) ? route('public.draw', ['uuid' => $record->uuid]) : route('public.draw-bulk', ['uuid' => $record->uuid]))
                    ->url(fn(EventPrize $record): string => route('public.draw-bulk', ['uuid' => $record->uuid]))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->form([
                        Hidden::make('event_id')
                            ->default(fn() => $this->record?->id),
                        TextInput::make('total_quantity')
                            ->numeric()
                            ->required(),
                        TextInput::make('remaining_quantity')
                            ->numeric()
                            ->required(),
                        TextInput::make('min_points_required')
                            ->numeric()
                            ->required(),
                        TextInput::make('split_draw')
                            ->numeric()
                            ->required(),
                        Select::make('draw_session_id')
                            ->options(function () {
                                return DrawSession::where('event_id', $this->record->id)
                                    ->orderBy('started_at', 'asc')
                                    ->pluck('name', 'id');
                            })
                            ->required(),
                    ]),
                DeleteAction::make()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
