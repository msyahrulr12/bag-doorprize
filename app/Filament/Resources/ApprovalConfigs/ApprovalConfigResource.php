<?php

namespace App\Filament\Resources\ApprovalConfigs;

use App\Filament\Resources\ApprovalConfigs\Pages\ManageApprovalConfigs;
use App\Models\ApprovalConfig;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use UnitEnum;
use BackedEnum;

class ApprovalConfigResource extends Resource
{
    protected static ?string $model = ApprovalConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('resource')
                    ->options(fn(): array => collect(array_merge(glob(app_path('Filament/Resources/*')), glob(app_path('Filament/Pages/*'))))
                        ->map(function($path) {
                            if (is_dir($path)) {
                                $resourceFile = collect(glob($path . '/*Resource.php'))->first();
                                if ($resourceFile) {
                                    return $resourceFile;
                                } else {
                                    return $path;
                                }
                            } elseif (str_ends_with($path, 'Resource.php')) {
                                return $path;
                            }
                            return $path;
                        })
                        ->filter()
                        ->unique()
                        ->mapWithKeys(fn($path) => [basename($path, '.php') => basename($path, '.php')])
                        ->toArray())
                    ->required()
                    ->placeholder('e.g. PrizeResource'),
                Select::make('action')
                    ->options([
                        'create' => 'Create',
                        'update' => 'Update',
                        'delete' => 'Delete',
                        'all' => 'All Actions',
                    ])
                    ->required(),
                Select::make('approver_role')
                    ->options(Role::pluck('name', 'name'))
                    ->required()
                    ->default('super_admin'),
                Toggle::make('is_enabled')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('resource')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approver_role')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApprovalConfigs::route('/'),
        ];
    }
}
