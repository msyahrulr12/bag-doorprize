<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\PointHistory;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PointHistoryTable extends TableWidget
{
    public array $account_ids = [];
    public bool $showExport = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => PointHistory::query()->whereIn('account_id', $this->account_ids))
            ->columns([
                TextColumn::make('amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('month')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('points')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
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
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('points')
                    ->numeric()
                    ->sortable()
                    ->summarize(
                        Summarizer::make()
                            ->label('Total Points')
                            ->using(fn($query) => $query->sum('points')),
                    ),
                TextColumn::make('status')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(\App\Filament\Exports\PointHistoryExporter::class)
                    ->label('Export CSV/Excel'),
                ExportAction::make('export_full')
                    ->label('Export All with Tickets')
                    ->exporter(\App\Filament\Exports\PointHistoryFullExporter::class)
                    ->color('info')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn() => $this->showExport)
                    ->columnMapping(false)
                    ->form([
                        \Filament\Forms\Components\Select::make('branch_ids')
                            ->label('Branches')
                            ->multiple()
                            ->options(\App\Models\Branch::pluck('branch_name', 'id'))
                            ->searchable()
                            ->preload(),
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date'),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('End Date'),
                    ])
                    ->modifyQueryUsing(function (Builder $query, array $data) {
                        return $query
                            ->when($data['branch_ids'], fn($q, $ids) => $q->whereHas('account', fn($sq) => $sq->whereIn('branch_id', $ids)))
                            ->when($data['start_date'], function ($q, $date) {
                                $start = \Carbon\Carbon::parse($date);
                                return $q->where(function ($sq) use ($start) {
                                    $sq->where('year', '>', $start->year)
                                        ->orWhere(function ($ssq) use ($start) {
                                            $ssq->where('year', $start->year)
                                                ->where('month', '>=', $start->month);
                                        });
                                });
                            })
                            ->when($data['end_date'], function ($q, $date) {
                                $end = \Carbon\Carbon::parse($date);
                                return $q->where(function ($sq) use ($end) {
                                    $sq->where('year', '<', $end->year)
                                        ->orWhere(function ($ssq) use ($end) {
                                            $ssq->where('year', $end->year)
                                                ->where('month', '<=', $end->month);
                                        });
                                });
                            });
                    }),
                Action::make('export_pdf')
                    ->label('Export PDF')
                    ->color('danger')
                    ->icon('heroicon-o-document-text')
                    ->action(function () {
                        $records = PointHistory::whereIn('account_id', $this->account_ids)->get();
                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.point-history', ['records' => $records]);
                        return response()->streamDownload(fn() => print ($pdf->output()), 'point-history.pdf');
                    }),
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
