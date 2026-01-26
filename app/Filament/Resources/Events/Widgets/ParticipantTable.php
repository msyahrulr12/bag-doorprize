<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Participant;
use App\Models\Account;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ParticipantTable extends TableWidget
{
    public ?Model $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Participant::query()
                    ->with(['lotteryTickets', 'account.customer'])
                    ->where('event_id', $this->record->id)
            )
            ->columns([
                TextColumn::make('event_id')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('account.customer.name')
                    ->label('Customer Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('account.account_number')
                    ->label('Account Number')
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
                TextColumn::make('total_points_snapshot')
                    ->state(fn(Participant $record) => $record->lotteryTickets()->where('status', 'ACTIVE')->sum('total_points'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lottery_tickets_count')
                    ->counts('lotteryTickets')
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
                CreateAction::make()
                    ->form([
                        Hidden::make('event_id')
                            ->default(fn() => $this->record->id),
                        Select::make('account_id')
                            ->label('Account')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return Account::with('customer')
                                    ->whereHas('customer', function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%");
                                    })
                                    ->orWhere('account_number', 'like', "%{$search}%")
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
