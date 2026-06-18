<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use App\Models\Participant;
use App\Models\LotteryTicket;
use Carbon\Carbon;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ParticipantTable extends TableWidget
{
    public ?Model $record = null;
    public array $account_ids = [];

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->query(function () {
                $query = Participant::query()
                    ->with(['account.customer', 'account.branch'])
                    ->withCount(['lotteryTickets' => function ($query) {
                        $query->where('status', LotteryTicket::STATUS_ACTIVE);
                    }])
                    ->whereHas('lotteryTickets', function ($query) {
                        $query->where('status', LotteryTicket::STATUS_ACTIVE);
                    })
                    ->withSum([
                        'lotteryTickets as active_points' => function ($query) {
                            $query->where('status', LotteryTicket::STATUS_ACTIVE);
                        }
                    ], 'total_points');

                if ($this->record) {
                    $statusEvent = $this->record->status;
                    if ($statusEvent == Event::STATUS_COMPLETED && $this->record->participants()->exists()) {
                        // For completed events, use the pivot table
                        $query->whereIn('participants.id', function ($subQuery) {
                            $subQuery->select('participant_id')
                                ->from('event_participant')
                                ->where('event_id', $this->record->id);
                        });
                    } else {
                        // For active or other events, use direct event_id filter
                        $query->where('participants.event_id', $this->record->id);
                    }
                }

                if (!empty($this->account_ids)) {
                    $query->whereIn('participants.account_id', $this->account_ids);
                }

                // Filter by user branches
                // if (!auth()->user()->hasRole('super_admin')) {
                //     $query->whereHas('account', function ($q) {
                //         $q->whereIn('branch_id', auth()->user()->branches->pluck('id'));
                //     });
                // }

                return $query;
            })
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('event_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('account.customer.name')
                    ->label('Customer Name')
                    // ->searchable(['customers.name'])
                    ->sortable(),
                TextColumn::make('account.account_number')
                    ->label('Account Number')
                    ->searchable(['participant_account_number'])
                    ->sortable(),
                TextColumn::make('account.branch.branch_name')
                    ->label('Branch Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('participant_name')
                    ->label('Participant Name (Snapshot)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_cif')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_account_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_phone_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('active_points')
                    ->label('Total Points')
                    ->default(0)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lottery_tickets_count')
                    ->label('Tickets')
                    ->badge()
                    ->color(fn(int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
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
            ->filters([
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\ParticipantExporter::class)
                    ->label('Export CSV/Excel'),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        $query = Participant::query()
                            ->with(['account.customer']);
                        if ($this->record) {
                            $query->where('event_id', $this->record->id);
                        }
                        if (!empty($this->account_ids)) {
                            $query->whereIn('account_id', $this->account_ids);
                        }
                        $records = $query->get();
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.participants', ['records' => $records]);
                        return response()->streamDownload(fn() => print ($pdf->output()), 'participants.pdf');
                    }),
            ])
            ->recordActions([
                Action::make('viewTickets')
                    ->label('View Tickets')
                    ->icon('heroicon-o-ticket')
                    ->color('info')
                    ->modalHeading(fn(Participant $record) => "Lottery Tickets for {$record->account->customer->name} (Count: " . ($record->lottery_tickets_count ?? 0) . ")")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(function (Participant $record) {
                        return [
                            TextEntry::make('tickets_count_summary')
                                ->label('Tickets Found')
                                ->state(fn(Participant $record) => $record->lottery_tickets_count ?? 0)
                                ->weight(FontWeight::Bold)
                                ->color('info'),
                            RepeatableEntry::make('lotteryTickets')
                                ->state(fn(Participant $record) => $record->lotteryTickets()->where('status', LotteryTicket::STATUS_ACTIVE)->get())
                                ->label('Active Tickets')
                                ->schema([
                                    TextEntry::make('id')
                                        ->label('ID')
                                        ->inlineLabel(),
                                    TextEntry::make('participant.account.account_number')
                                        ->label('Account Number')
                                        ->inlineLabel(),
                                    TextEntry::make('month')
                                        ->formatStateUsing(fn($state) => Carbon::create()->month($state)->format('F'))
                                        ->label('Month')
                                        ->inlineLabel(),
                                    TextEntry::make('year')
                                        ->label('Year')
                                        ->inlineLabel(),
                                    TextEntry::make('total_points')
                                        ->label('Points')
                                        ->numeric()
                                        ->inlineLabel()
                                        ->formatStateUsing(fn($state, $record) => $state - (\DB::table('winners')->where('lottery_ticket_id', $record->id)->count())),
                                    TextEntry::make('range_start')
                                        ->label('Start')
                                        ->inlineLabel()
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('range_end')
                                        ->label('End')
                                        ->inlineLabel()
                                        ->weight(FontWeight::Bold),
                                    TextEntry::make('status')
                                        ->badge()
                                        ->color(fn(string $state): string => match ($state) {
                                            'ACTIVE' => 'success',
                                            'RESET' => 'danger',
                                            'COMPLETED' => 'info',
                                            default => 'gray',
                                        })
                                        ->inlineLabel(),
                                ])
                                ->columns(3)
                                ->grid(1)
                        ];
                    })
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
