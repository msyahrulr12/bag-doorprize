<?php

namespace App\Filament\Resources\Events\Tables;

use App\Filament\Exports\EventExporter;
use App\Filament\Imports\EventImporter;
use App\Models\Event;
use App\Services\EventService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_code')
                    ->searchable(),
                TextColumn::make('event_name')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable()
                    ->state(function ($record): string {
                        return Event::EVENT_STATUS[$record['status']] ?? 'N/A';
                    }),
                TextColumn::make('event_started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_ended_at')
                    ->dateTime()
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
                Action::make('transferData')
                    ->label('Transfer Data')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('warning')
                    ->form([
                        Select::make('source_event_id')
                            ->label('Source Event')
                            ->options(fn(Event $record) => Event::where('id', '!=', $record->id)
                                ->where('status', Event::STATUS_COMPLETED)
                                ->pluck('event_name', 'id'))
                            ->required()
                            ->searchable()
                    ])
                    ->action(function (Event $record, array $data, EventService $service) {
                        $service->transferData($data['source_event_id'], $record->id);

                        Notification::make()
                            ->title('Data transferred successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Transfer Participants & Tickets')
                    ->modalDescription('This will move all participants and lottery tickets from the source event to this event. History will be preserved in the event log.')
                    ->visible(fn(Event $record) => $record->status === Event::STATUS_DRAFT || $record->status === Event::STATUS_ACTIVE),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()->exporter(EventExporter::class),
                ImportAction::make()->importer(EventImporter::class),
            ])
            ->defaultSort('event_started_at', 'desc');
    }
}
