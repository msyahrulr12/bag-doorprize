<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use App\Models\Participant;
use App\Models\Account;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Tables\Columns\TextColumn;
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
                if ($this->record->status == Event::STATUS_ACTIVE) {
                    // For active events, use direct event_id filter
                    $query = Participant::query()
                        ->select('participants.*')
                        ->with(['account.customer'])
                        ->withCount('lotteryTickets');

                    if ($this->record) {
                        $query->where('event_id', $this->record->id);
                    }
                } else {
                    // For inactive events, use the pivot table
                    $query = Participant::query()
                        ->select('participants.*')
                        ->with(['account.customer'])
                        ->withCount('lotteryTickets')
                        ->whereIn('id', function ($subQuery) {
                        $subQuery->select('participant_id')
                            ->from('event_participant')
                            ->where('event_id', $this->record->id);
                    });
                }

                if (!empty($this->account_ids)) {
                    $query->whereIn('account_id', $this->account_ids);
                }

                // Filter by user branches
                if (!auth()->user()->hasRole('super_admin')) {
                    $query->whereHas('account', function ($q) {
                        $q->whereIn('branch_id', auth()->user()->branches->pluck('id'));
                    });
                }

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
                    ->searchable(['customers.name'])
                    ->sortable(),
                TextColumn::make('account.account_number')
                    ->label('Account Number')
                    ->searchable(['accounts.account_number'])
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
                    ->getStateUsing(function (Participant $record) {
                        // Use a cached query to avoid N+1
                        return DB::table('lottery_tickets')
                            ->where('participant_id', $record->id)
                            ->where('status', 'ACTIVE')
                            ->sum('total_points');
                    })
                    ->numeric()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('lottery_tickets', function ($join) {
                                $join->on('participants.id', '=', 'lottery_tickets.participant_id')
                                    ->where('lottery_tickets.status', '=', 'ACTIVE');
                            })
                            ->groupBy('participants.id')
                            ->orderBy(DB::raw('SUM(COALESCE(lottery_tickets.total_points, 0))'), $direction);
                    }),
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
                //
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
                CreateAction::make()
                    ->form([
                        Hidden::make('event_id')
                            ->default(fn() => $this->record?->id),
                        Select::make('account_id')
                            ->label('Account')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return Account::with('customer')
                                    ->when(!auth()->user()->hasRole('super_admin'), function ($query) {
                                        $query->whereIn('branch_id', auth()->user()->branches->pluck('id'));
                                    })
                                    ->where(function ($q) use ($search) {
                                        $q->whereHas('customer', function ($sub) use ($search) {
                                            $sub->where('name', 'ilike', "%{$search}%");
                                        })
                                            ->orWhere('account_number', 'ilike', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($account) => [$account->id => $account->customer->name . ' - ' . $account->account_number]);
                            })
                            ->getOptionLabelUsing(fn($value): ?string => Account::find($value)?->customer?->name . ' - ' . Account::find($value)?->account_number)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                $account = Account::with('customer')->find($state);
                                if ($account) {
                                    $set('participant_name', $account->customer->name);
                                    $set('participant_cif', $account->customer->cif);
                                    $set('participant_account_number', $account->account_number);
                                    $set('participant_email', $account->customer->email);
                                    $set('participant_phone_number', $account->customer->phone_number);
                                }
                            })
                            ->preload(),
                        TextInput::make('participant_name')
                            ->required(),
                        TextInput::make('participant_cif')
                            ->required(),
                        TextInput::make('participant_account_number')
                            ->required(),
                        TextInput::make('participant_email')
                            ->email()
                            ->required(),
                        TextInput::make('participant_phone_number')
                            ->tel()
                            ->required(),
                        TextInput::make('total_points_snapshot')
                            ->numeric()
                            ->required(),
                        TextInput::make('range_start')
                            ->numeric()
                            ->required(),
                        TextInput::make('range_end')
                            ->numeric()
                            ->required(),
                    ])
            ])
            ->recordActions([
                Action::make('viewTickets')
                    ->label('View Tickets')
                    ->icon('heroicon-o-ticket')
                    ->color('info')
                    ->modalHeading(fn(Participant $record) => "Lottery Tickets for {$record->account->customer->name} (Count: " . $record->lotteryTickets()->count() . ")")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(function (Participant $record) {
                        return [
                            TextEntry::make('tickets_count_summary')
                                ->label('Tickets Found')
                                ->state(fn(Participant $record) => $record->lotteryTickets()->count())
                                ->weight(FontWeight::Bold)
                                ->color('info'),
                            RepeatableEntry::make('lotteryTickets')
                                ->state(fn(Participant $record) => $record->lotteryTickets()->get())
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
                                        ->inlineLabel(),
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
