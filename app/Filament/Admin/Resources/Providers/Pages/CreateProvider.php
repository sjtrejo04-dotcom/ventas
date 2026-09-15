<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Providers\Pages;

use App\Filament\Admin\Resources\Providers\ProviderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProvider extends CreateRecord
{
    protected static string $resource = ProviderResource::class;
}
