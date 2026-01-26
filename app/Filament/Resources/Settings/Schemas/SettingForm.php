<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group')
                    ->required()
                    ->default('general'),
                TextInput::make('key')
                    ->required(),
                \Filament\Forms\Components\KeyValue::make('value')
                    ->label('Value (JSON)')
                    ->visible(fn($get) => $get('type') === 'json')
                    ->formatStateUsing(fn($state) => is_string($state) ? json_decode($state, true) : $state)
                    ->dehydrateStateUsing(fn($state) => json_encode($state))
                    ->columnSpanFull(),
                Textarea::make('value')
                    ->visible(fn($get) => $get('type') !== 'json')
                    ->columnSpanFull(),
                \Filament\Forms\Components\Select::make('type')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'json' => 'JSON',
                    ])
                    ->required()
                    ->live()
                    ->default('string'),
            ]);
    }
}
