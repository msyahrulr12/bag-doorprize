<?php

namespace App\Filament\Resources\Settings\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('group')
                    ->options(Setting::GROUP)
                    ->required()
                    ->default('general'),
                TextInput::make('key')
                    ->required(),
                Select::make('type')
                    ->options(Setting::TYPE)
                    ->required()
                    ->live(),
                Textarea::make('value')
                    ->columnSpanFull(),
            ]);
    }
}
