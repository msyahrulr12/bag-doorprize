<?php

namespace App\Filament\Resources\DrawSessions;

use App\Filament\Resources\DrawSessions\Pages\CreateDrawSession;
use App\Filament\Resources\DrawSessions\Pages\EditDrawSession;
use App\Filament\Resources\DrawSessions\Pages\ListDrawSessions;
use App\Filament\Resources\DrawSessions\RelationManagers\EventsRelationManager;
use App\Filament\Resources\DrawSessions\Schemas\DrawSessionForm;
use App\Filament\Resources\DrawSessions\Tables\DrawSessionsTable;
use App\Models\DrawSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DrawSessionResource extends Resource
{
    protected static ?string $model = DrawSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return DrawSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrawSessionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrawSessions::route('/'),
            'create' => CreateDrawSession::route('/create'),
            'edit' => EditDrawSession::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
