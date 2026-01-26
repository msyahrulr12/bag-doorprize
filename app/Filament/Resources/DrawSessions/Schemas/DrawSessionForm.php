<?php

namespace App\Filament\Resources\DrawSessions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DrawSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('event_id')
                    ->relationship(name: 'event', titleAttribute: 'event_name'),
                TextInput::make('name')
                    ->required(),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('ended_at'),
                TextInput::make('total_lottery_generated')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('status')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(10),
            ]);
    }
}
