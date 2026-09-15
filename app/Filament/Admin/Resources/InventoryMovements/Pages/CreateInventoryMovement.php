<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryMovements\Pages;

use App\Filament\Admin\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryMovement extends CreateRecord
{
    protected static string $resource = InventoryMovementResource::class;
}
