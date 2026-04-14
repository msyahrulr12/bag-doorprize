<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make()
                        ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Event::STATUS_ACTIVE)),
            'all' => Tab::make(),
            'inactive' => Tab::make()
                        ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Event::STATUS_INACTIVE)),
            'completed' => Tab::make()
                        ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Event::STATUS_COMPLETED)),
            'draft' => Tab::make()
                        ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Event::STATUS_DRAFT)),
        ];
    }
}
