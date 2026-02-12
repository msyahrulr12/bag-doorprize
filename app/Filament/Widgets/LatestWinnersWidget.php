<?php

namespace App\Filament\Widgets;

use App\Models\Winner;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestWinnersWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Winner::query()->latest('drawn_at')->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('participant_name')
                    ->label('Winner Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('prize_name')
                    ->label('Prize')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('winning_number')
                    ->label('Coupon Number')
                    ->copyable(),
                Tables\Columns\TextColumn::make('drawn_at')
                    ->label('Drawn Time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        Winner::STATUS_CLAIMED => 'success',
                        Winner::STATUS_PENDING => 'warning',
                        Winner::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->heading('Latest Winners');
    }
}
