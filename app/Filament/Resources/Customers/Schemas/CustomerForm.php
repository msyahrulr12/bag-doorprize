<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Branch;
use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship(
                        name: 'branch',
                        titleAttribute: 'branch_name',
                        modifyQueryUsing: fn(Builder $query) => $query
                            ->where('status', Branch::STATUS_ACTIVE)
                            ->when(
                                !auth()->user()->hasRole('super_admin'),
                                fn($q) => $q->whereIn('id', auth()->user()->branches->pluck('id'))
                            )
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('cif')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone_number')
                    ->tel()
                    ->required(),
                Textarea::make('address')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('total_point_sum')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('redeemed_points')
                    ->required()
                    ->numeric()
                    ->default(0),
                DatePicker::make('date_of_birth')
                    ->required(),
                Select::make('status')
                    ->required()
                    ->options(Customer::STATUS),
            ]);
    }
}
