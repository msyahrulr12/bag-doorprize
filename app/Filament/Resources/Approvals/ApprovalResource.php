<?php

namespace App\Filament\Resources\Approvals;

use App\Filament\Resources\Approvals\Pages\ManageApprovals;
use App\Models\Approval;
use App\Services\ApprovalService;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
use BackedEnum;

class ApprovalResource extends Resource
{
    protected static ?string $model = Approval::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Details')
                    ->schema([
                        TextInput::make('resource')
                            ->readOnly(),
                        TextInput::make('action')
                            ->readOnly(),
                        TextInput::make('status')
                            ->readOnly(),
                        ViewField::make('diff')
                            ->view('filament.resources.approvals.diff-view')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => Approval::query()->orderBy('created_at', 'desc'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Requester')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('resource')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'create' => 'success',
                        'update' => 'warning',
                        'delete' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        Approval::STATUS_PENDING => 'warning',
                        Approval::STATUS_APPROVED => 'success',
                        Approval::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Approval::STATUS_PENDING => 'Pending',
                        Approval::STATUS_APPROVED => 'Approved',
                        Approval::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->visible(fn(Approval $record) => $record->status === Approval::STATUS_PENDING && ApprovalService::canApprove($record))
                    ->action(function (Approval $record) {
                        if (ApprovalService::approve($record)) {
                            Notification::make()
                                ->title('Request approved and executed.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Failed to execute the request.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->required(),
                    ])
                    ->visible(fn(Approval $record) => $record->status === Approval::STATUS_PENDING && ApprovalService::canApprove($record))
                    ->action(function (Approval $record, array $data) {
                        ApprovalService::reject($record, $data['reason']);
                        Notification::make()
                            ->title('Request rejected.')
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApprovals::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', Approval::STATUS_PENDING)->count();
    }
}
