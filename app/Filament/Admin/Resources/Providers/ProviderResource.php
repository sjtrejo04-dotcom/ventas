<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Providers;

use App\Filament\Admin\Resources\Providers\Pages\CreateProvider;
use App\Filament\Admin\Resources\Providers\Pages\EditProvider;
use App\Filament\Admin\Resources\Providers\Pages\ListProviders;
use App\Filament\Admin\Resources\Providers\Schemas\ProviderForm;
use App\Filament\Admin\Resources\Providers\Tables\ProvidersTable;
use App\Models\Provider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Proveedor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Proveedores';
    }

    public static function getNavigationLabel(): string
    {
        return 'Proveedores';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Finanzas & Contabilidad';
    }

    public static function form(Schema $schema): Schema
    {
        return ProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvidersTable::configure($table);
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
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'edit' => EditProvider::route('/{record}/edit'),
        ];
    }
}
