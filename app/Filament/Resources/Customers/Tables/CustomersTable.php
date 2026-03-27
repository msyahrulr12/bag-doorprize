<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Filament\Exports\CustomerExporter;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\LotteryTicket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('cif')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('redeemed_points')
                    ->numeric()
                    ->state(fn(Customer $record) => $record->accounts->flatMap->participants->flatMap->lotteryTickets->where('status', LotteryTicket::STATUS_ACTIVE)->sum('total_points'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch')
                    ->relationship(
                        'branch',
                        'branch_name',
                        modifyQueryUsing: fn(Builder $query) => $query
                            ->where('status', Branch::STATUS_ACTIVE)
                            ->when(
                                !auth()->user()->hasRole('super_admin'),
                                fn($q) => $q->whereIn('id', auth()->user()->branches->pluck('id'))
                            )
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ])
            ->headerActions([
                ExportAction::make()->exporter(CustomerExporter::class),
            ]);
    }
}
