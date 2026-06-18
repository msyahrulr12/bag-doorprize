<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\DrawSession;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

class DrawSessionTable extends TableWidget
{
    public ?Model $record = null;
    public ?int $event_id = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = DrawSession::query()->withCount(['winners', 'temporaryWinners'])->orderBy('started_at', 'asc');
                if ($this->record) {
                    $query->where('event_id', $this->record->id);
                } elseif ($this->event_id) {
                    $query->where('event_id', $this->event_id);
                }
                return $query;
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'INACTIVE' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('winners_count')
                    ->label('Total Winners')
                    ->getStateUsing(fn(DrawSession $record): int => $record->winners_count + $record->temporary_winners_count)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\DrawSessionExporter::class)
                    ->label('Export CSV/Excel'),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        $query = DrawSession::query()->withCount(['winners', 'temporaryWinners'])->orderBy('started_at', 'asc');
                        if ($this->record) {
                            $query->where('event_id', $this->record->id);
                        } elseif ($this->event_id) {
                            $query->where('event_id', $this->event_id);
                        }
                        $records = $query->get();
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.draw-sessions', ['records' => $records]);
                        return response()->streamDownload(fn() => print($pdf->output()), 'draw-sessions.pdf');
                    }),
                CreateAction::make()
                    ->form([
                        Hidden::make('event_id')
                            ->default(fn() => $this->record?->id),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(fn() => DrawSession::DRAW_SESSION_STATUS)
                            ->default('ACTIVE')
                            ->required(),
                        DateTimePicker::make('started_at')
                            ->default(now()),
                        DateTimePicker::make('ended_at'),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(fn() => DrawSession::DRAW_SESSION_STATUS)
                            ->required(),
                        DateTimePicker::make('started_at'),
                        DateTimePicker::make('ended_at'),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
