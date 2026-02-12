<?php

namespace App\Filament\Resources\DrawSessions\Pages;

use App\Filament\Resources\DrawSessions\DrawSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDrawSession extends CreateRecord
{
    use \App\Traits\InteractsWithApprovals;

    protected static string $resource = DrawSessionResource::class;
}
