<?php

namespace App\Filament\Resources\DrawSessions\Pages;

use App\Filament\Resources\DrawSessions\DrawSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDrawSessions extends ListRecords
{
    protected static string $resource = DrawSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
