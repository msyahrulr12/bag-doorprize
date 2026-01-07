<?php

namespace App\Filament\Resources\Prizes\Schemas;

use App\Models\Prize;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class PrizeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('prize_code')
                    ->required(),
                TextInput::make('prize_name')
                    ->required(),
                FileUpload::make('prize_image')
                    ->label('Prize Image')
                    ->required()
                    ->columnSpanFull()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                    ->maxSize(2048)
                    ->disk('public')
                    ->directory('uploads'),
                Select::make('tier')
                    ->required()
                    ->options(Prize::PRIZE_TIER),
                TextInput::make('value')
                    ->label('Nilai Hadiah (Rp)')
                    ->required()
                    ->currencyMask(thousandSeparator: '.',decimalSeparator: ',',precision: 2),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->rows(10),
            ]);
    }
}
