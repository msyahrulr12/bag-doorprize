<?php

namespace App\Filament\Pages\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class CustomerReportingTable extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->with(['branch'])
                    ->when(!auth()->user()->hasRole('super_admin'), function (Builder $query) {
                        $query->whereIn('branch_id', auth()->user()->branches->pluck('id'));
                    })
            )
            ->columns([
                Tables\Columns\TextColumn::make('cif')
                    ->label('CIF')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.branch_name')
                    ->label('Branch')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('view_report')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function (Customer $record) {
                        $this->dispatch('customerSelected', customerId: $record->id);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
