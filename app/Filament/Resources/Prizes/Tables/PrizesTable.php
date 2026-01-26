<?php

namespace App\Filament\Resources\Prizes\Tables;

use App\Filament\Exports\PrizeExporter;
use App\Filament\Imports\EventImporter;
use App\Models\Prize;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PrizesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prize_code')
                    ->searchable(),
                TextColumn::make('prize_name')
                    ->searchable(),
                TextColumn::make('tier')
                    ->searchable()
                    ->state(function ($record): string {
                        return Prize::PRIZE_TIER[$record['tier']] ?? 'Common';
                    }),
                TextColumn::make('value')
                    ->numeric()
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()->exporter(PrizeExporter::class),
                ImportAction::make()->importer(EventImporter::class),
            ]);
    }
}
