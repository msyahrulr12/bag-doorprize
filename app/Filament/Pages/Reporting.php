<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\Customer;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

use Livewire\Attributes\On;

class Reporting extends Page implements HasForms
{
    use InteractsWithForms;

    #[On('customerSelected')]
    public function selectCustomer(int $customerId): void
    {
        $this->customerId = $customerId;
        $this->showExport = false;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Reports';
    protected static ?string $title = 'Reporting Dashboard';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.reporting';

    private $showExport = true;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export_full')
                ->label('Export All Point Histories')
                ->exporter(\App\Filament\Exports\PointHistoryFullExporter::class)
                ->color('info')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn() => $this->customerId === null),
        ];
    }

    public function getDescription(): ?string
    {
        return 'Comprehensive analytics and data tracking for Customers and Events.';
    }

    public ?int $customerId = null;

    public function mount(): void
    {
        $this->customerId = request()->query('customer_id');
    }



    public function resetCustomer(): void
    {
        $this->customerId = null;
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}