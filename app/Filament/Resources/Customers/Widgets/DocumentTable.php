<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\AccountDocument;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class DocumentTable extends TableWidget
{
    public ?Model $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => AccountDocument::query()->where('customer_id', $this->record->id))
            ->columns([
                TextColumn::make('account.account_number')
                    ->label('Account'),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('filename')
                    ->searchable(),
                TextColumn::make('period')
                    ->date('M Y')
                    ->sortable(),
                IconColumn::make('is_merged')
                    ->boolean(),
                TextColumn::make('file_name_t24')
                    ->copyable()
                    ->copyMessage('Filename copied'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'PENDING' => 'gray',
                        'PROCESSING' => 'warning',
                        'COMPLETED' => 'success',
                        'FAILED' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('has_stored_to_sftp')
                    ->label('T24')
                    ->boolean(),
                TextColumn::make('version')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_latest')
                    ->boolean()
                    ->label('Latest'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->label('Download')
                    ->color('success')
                    ->action(function (AccountDocument $record) {
                        try {
                            $disk = Storage::disk('core_t24_sftp');
                            $dataPdf = $disk->get($record?->file_path_t24);
                            $filenamePdf = $record?->file_name_t24;

                            return response()->streamDownload(function () use ($dataPdf) {
                                echo $dataPdf;
                            }, $filenamePdf, [
                                'Content-Type' => 'application/pdf'
                            ]);
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Download Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->label('Resend')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (AccountDocument $record) {
                        try {
                            $service = app(\App\Services\BankStatementService::class);
                            $success = $service->resend($record);

                            if ($success) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Resend Successful')
                                    ->body('E-statement has been regenerated and sent to T24 SFTP.')
                                    ->success()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title('Resend Failed')
                                    ->body('Could not send updated e-statement to SFTP.')
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            dd($e->getTraceAsString());
                            \Filament\Notifications\Notification::make()
                                ->title('Resend Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
