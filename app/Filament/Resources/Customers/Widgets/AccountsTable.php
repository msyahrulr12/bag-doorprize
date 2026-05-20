<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Account;
use App\Models\Customer;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AccountsTable extends TableWidget
{
    public ?Customer $customer = null;

    public function table(Table $table): Table
    {
        $customerIds = $this->customer->accounts->pluck('id')->toArray();
        return $table
            ->query(fn(): Builder => Account::query()->whereIn('id', $customerIds))
            ->columns([
                TextColumn::make('customer.cif')
                    ->searchable()
                    ->sortable()
                    ->label('No. CIF'),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Nama Customer'),
                TextColumn::make('account_number')
                    ->searchable()
                    ->sortable()
                    ->label('Nomor Rekening'),
                TextColumn::make('account_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('current_balance')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('branch.branch_name')
                    ->searchable()
                    ->sortable()
                    ->label('Cabang'),
                TextColumn::make('status')
                    ->searchable()
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
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->summaries(
                pageCondition: false
            )
            ->paginated();
        ;
    }
}
