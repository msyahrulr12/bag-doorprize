<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event_code')
                    ->required(),
                TextInput::make('event_name')
                    ->required(),
                FileUpload::make('event_image')
                    ->label('Event Image')
                    ->required()
                    ->columnSpanFull()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('uploads'),
                Select::make('status')
                    ->required()
                    ->options(Event::EVENT_STATUS),
                DateTimePicker::make('event_started_at'),
                DateTimePicker::make('event_ended_at'),
                FileUpload::make('public_draw_background')
                    ->label('Public Draw Background')
                    ->columnSpanFull()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('uploads'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
