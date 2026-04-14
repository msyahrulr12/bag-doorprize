<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\EventPrize;
use App\Models\Prize;
use Event;
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
use Filament\Forms\Set;

class EventPrizeTable extends TableWidget
{
    public ?Model $record = null;
    public ?int $event_id = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = EventPrize::query();
                if ($this->record) {
                    $query->where('event_id', $this->record->id);
                } elseif ($this->event_id) {
                    $query->where('event_id', $this->event_id);
                }
                return $query;
            })
            ->columns([
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
                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('remaining_quantity')
                    ->label('Remaining Qty')
                    ->numeric()
                    ->sortable()
                    ->searchable(),
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
                        return response()->streamDownload(fn() => print ($pdf->output()), 'event-prizes.pdf');
                    }),
                CreateAction::make()
                    ->form([
                        Hidden::make('event_id')
                            ->default(fn() => $this->record?->id),
                        Select::make('prize_id')
                            ->relationship(
                                'prize',
                                'prize_name',
                                fn(Builder $query) => $query->whereNotIn('id', EventPrize::where('event_id', $this->record?->id ?? $this->event_id)->pluck('prize_id'))
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
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
                    ->url(fn(EventPrize $record): string => in_array($record->prize->tier, [Prize::TIER_GRAND_PRIZE]) ? route('public.draw', ['uuid' => $record->uuid]) : route('public.draw-bulk', ['uuid' => $record->uuid]))
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
