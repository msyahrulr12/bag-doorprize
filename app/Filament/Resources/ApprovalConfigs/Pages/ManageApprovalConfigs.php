<?php

namespace App\Filament\Resources\ApprovalConfigs\Pages;

use App\Filament\Resources\ApprovalConfigs\ApprovalConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageApprovalConfigs extends ManageRecords
{
    protected static string $resource = ApprovalConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
