<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $recordTitleAttribute = 'filename';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('account_id')
                    ->relationship('account', 'account_number')
                    ->searchable()
                    ->preload(),
                TextInput::make('type')
                    ->required()
                    ->default('e-statement'),
                TextInput::make('filename')
                    ->required(),
                DatePicker::make('period')
                    ->required(),
                Toggle::make('is_merged'),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ])
                    ->required()
                    ->default('pending'),
                TextInput::make('path')
                    ->required()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
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
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
