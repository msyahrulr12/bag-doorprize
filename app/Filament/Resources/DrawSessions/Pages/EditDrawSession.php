<?php

namespace App\Filament\Resources\DrawSessions\Pages;

use App\Filament\Resources\DrawSessions\DrawSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDrawSession extends EditRecord
{
    use \App\Traits\InteractsWithApprovals;

    protected static string $resource = DrawSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RestoreAction::make(),
        ];
    }
}
