<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Providers\Pages;

use App\Filament\Admin\Resources\Providers\ProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
