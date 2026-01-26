<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\AccountDocument;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DocumentTable extends TableWidget
{
    public ?Model $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => AccountDocument::query()->where('customer_id', $this->record->id))
            ->columns([
                TextColumn::make('account.account_number')
                    ->label('Account'),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('filename')
                    ->searchable(),
                TextColumn::make('period')
                    ->date('M Y')
                    ->sortable(),
                IconColumn::make('is_merged')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
