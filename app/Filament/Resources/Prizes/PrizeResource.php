<?php

namespace App\Filament\Resources\Prizes;

use App\Filament\Resources\Prizes\Pages\CreatePrize;
use App\Filament\Resources\Prizes\Pages\EditPrize;
use App\Filament\Resources\Prizes\Pages\ListPrizes;
use App\Filament\Resources\Prizes\Schemas\PrizeForm;
use App\Filament\Resources\Prizes\Tables\PrizesTable;
use App\Models\Prize;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PrizeResource extends Resource
{
    protected static ?string $model = Prize::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Gift;

    protected static string|UnitEnum|null $navigationGroup = 'Event & Prize';

    protected static ?string $recordTitleAttribute = 'prize_name';

    public static function form(Schema $schema): Schema
    {
        return PrizeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrizesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrizes::route('/'),
            'create' => CreatePrize::route('/create'),
            'edit' => EditPrize::route('/{record}/edit'),
        ];
    }
}
