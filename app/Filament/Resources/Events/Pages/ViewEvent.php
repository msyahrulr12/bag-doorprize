<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\Widgets\EventPrizeTable;
use App\Filament\Resources\Events\Widgets\DrawSessionTable;
use App\Filament\Resources\Events\Widgets\LotteryTicketTable;
use App\Filament\Resources\Events\Widgets\ParticipantTable;
use App\Models\Event;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Widgets\TicketVerificationWidget;
use App\Filament\Resources\Events\Widgets\EligibleTicketWidget;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Tables\Columns\TextColumn;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

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
                        Tab::make('Detail')
                            ->schema([
                                $this->hasInfolist() // This method returns `true` if the page has an infolist defined
                                    ? $this->getInfolistContentComponent() // This method returns a component to display the infolist that is defined in this resource
                                    : $this->getFormContentComponent(), // This method returns a component to display the form that is defined in this resource
                                $this->getRelationManagersContentComponent()
                            ]),
                        Tab::make('Draw Session')
                            ->schema([
                                Livewire::make(DrawSessionTable::class, [
                                    'record' => $this->getRecord(),
                                ]),
                            ]),
                        Tab::make('Event Prize')
                            ->schema([
                                Livewire::make(EventPrizeTable::class, [
                                    'record' => $this->getRecord(),
                                ]),
                            ]),
                        Tab::make('Participant')
                            ->schema([
                                Livewire::make(ParticipantTable::class, [
                                    'record' => $this->getRecord(),
                                ]),
                            ]),
                        Tab::make('Ticket Verification')
                            ->schema([
                                Livewire::make(TicketVerificationWidget::class, [
                                    'record' => $this->getRecord(),
                                ]),
                            ]),
                        Tab::make('Eligible Ticket')
                            ->schema([
                                Livewire::make(EligibleTicketWidget::class, [
                                    'record' => $this->getRecord(),
                                ]),
                            ])
                    ]),
            ]);
    }
}
