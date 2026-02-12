<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\LotteryTicket;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LotteryTicketTable extends TableWidget
{
    public array $account_ids = [];
    public ?int $event_id = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = LotteryTicket::query();
                if (!empty($this->account_ids)) {
                    $query->whereHas('participant', fn($q) => $q->whereIn('account_id', $this->account_ids));
                }
                if ($this->event_id) {
                    $query->where('event_id', $this->event_id);
                }
                return $query;
            })
            ->columns([
                TextColumn::make('event_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('participant_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_points')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('range_start')
                    ->searchable(),
                TextColumn::make('range_end')
                    ->searchable(),
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
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('month')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\LotteryTicketExporter::class)
                    ->label('Export CSV/Excel'),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        $query = LotteryTicket::query();
                        if (!empty($this->account_ids)) {
                            $query->whereHas('participant', fn($q) => $q->whereIn('account_id', $this->account_ids));
                        }
                        if ($this->event_id) {
                            $query->where('event_id', $this->event_id);
                        }
                        $records = $query->get();
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.lottery-tickets', ['records' => $records]);
                        return response()->streamDownload(fn() => print ($pdf->output()), 'lottery-tickets.pdf');
                    }),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
