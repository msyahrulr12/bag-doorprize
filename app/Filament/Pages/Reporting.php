<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Event;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class Reporting extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Reports';
    protected static ?string $title = 'Reporting Dashboard';
    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.reporting';

    public function getDescription(): ?string
    {
        return 'Comprehensive analytics and data tracking for Customers and Events.';
    }

    public ?array $data = [];

    public ?int $customerId = null;
    public ?int $eventId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Reporting Tabs')
                    ->tabs([
                        Tab::make('Customer Reporting')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Select Customer')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search): array => Customer::where('status', Customer::STATUS_ACTIVE)
                                        ->where(function (Builder $builder) use ($search) {
                                            $builder->where('name', 'ilike', "%{$search}%")
                                                ->orWhere('cif', 'ilike', "%{$search}%");
                                        })
                                        ->whereIn('branch_id', auth()->user()->branches->pluck('id')->toArray())
                                        ->limit(50)
                                        ->pluck('name', 'id')
                                        ->toArray())
                                    ->getOptionLabelUsing(fn($value): ?string => Customer::find($value)?->name)
                                    ->noSearchResultsMessage('No customers found.')
                                    ->live()
                                    ->afterStateUpdated(fn($state) => $this->customerId = $state),
                            ]),
                        Tab::make('Event Reporting')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                Select::make('event_id')
                                    ->label('Select Event')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search): array => Event::where('event_name', 'ilike', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('event_name', 'id')
                                        ->toArray())
                                    ->getOptionLabelUsing(fn($value): ?string => Event::find($value)?->event_name)
                                    ->live()
                                    ->afterStateUpdated(fn($state) => $this->eventId = $state),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // We can add widgets here, but we'll probably need to pass parameters to them
        ];
    }
}