<?php

namespace App\Filament\Widgets;

use App\Models\FailedUpload;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Actions\Action;

class LatestFailedUploadsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FailedUpload::query()->where('status', 'failed')->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Failed At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (FailedUpload $record) {
                        \Illuminate\Support\Facades\Artisan::call('app:trigger-failed-upload-command', [
                            '--id' => $record->id,
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Retry command triggered')
                            ->success()
                            ->send();
                    }),
            ])
            ->heading('Failed Bank Statement Uploads');
    }

    public static function canView(): bool
    {
        return FailedUpload::where('status', 'failed')->exists();
    }
}
