<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryMovements;

use App\Filament\Admin\Resources\InventoryMovements\Pages\CreateInventoryMovement;
use App\Filament\Admin\Resources\InventoryMovements\Pages\EditInventoryMovement;
use App\Filament\Admin\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Filament\Admin\Resources\InventoryMovements\Pages\ViewInventoryMovement;
use App\Filament\Admin\Resources\InventoryMovements\Schemas\InventoryMovementForm;
use App\Filament\Admin\Resources\InventoryMovements\Schemas\InventoryMovementInfolist;
use App\Filament\Admin\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?string $recordTitleAttribute = 'concept';

    public static function getModelLabel(): string
    {
        return 'Movimiento de Inventario';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Movimientos de Inventario';
    }

    public static function getNavigationLabel(): string
    {
        return 'Movimientos de Inventario';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Inventario';
    }

    public static function form(Schema $schema): Schema
    {
        return InventoryMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'create' => CreateInventoryMovement::route('/create'),
            'view' => ViewInventoryMovement::route('/{record}'),
            'edit' => EditInventoryMovement::route('/{record}/edit'),
        ];
    }
}


