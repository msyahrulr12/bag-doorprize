<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\Widgets\DocumentTable;
use App\Filament\Resources\Customers\Widgets\PdfBankStatement;
use App\Filament\Resources\Customers\Widgets\PointHistoryTable;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('Customer')
                            ->schema([
                                $this->hasInfolist() // This method returns `true` if the page has an infolist defined
                                ? $this->getInfolistContentComponent() // This method returns a component to display the infolist that is defined in this resource
                                : $this->getFormContentComponent(), // This method returns a component to display the form that is defined in this resource
                                $this->getRelationManagersContentComponent()
                            ]),
                        Tab::make('Point History')
                            ->schema([
                                Livewire::make(PointHistoryTable::class, [
                                    'account_ids' => $this->getRecord()->accounts->pluck('id')->toArray(),
                                ]),
                            ]),
                        Tab::make('Bank Statement')
                            ->schema([
                                Livewire::make(PdfBankStatement::class, [
                                    'customer' => $this->getRecord()
                                ])
                            ]),
                        Tab::make('Document')
                            ->schema([
                                Livewire::make(DocumentTable::class, [
                                    'record' => $this->getRecord(),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
