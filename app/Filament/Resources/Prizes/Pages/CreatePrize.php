<?php

namespace App\Filament\Resources\Prizes\Pages;

use App\Filament\Resources\Prizes\PrizeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrize extends CreateRecord
{
    use \App\Traits\InteractsWithApprovals;
    protected static string $resource = PrizeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
