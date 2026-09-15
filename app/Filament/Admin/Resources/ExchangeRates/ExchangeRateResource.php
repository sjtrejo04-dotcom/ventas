<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExchangeRates;

use App\Filament\Admin\Resources\ExchangeRates\Pages\CreateExchangeRate;
use App\Filament\Admin\Resources\ExchangeRates\Pages\EditExchangeRate;
use App\Filament\Admin\Resources\ExchangeRates\Pages\ListExchangeRates;
use App\Filament\Admin\Resources\ExchangeRates\Schemas\ExchangeRateForm;
use App\Filament\Admin\Resources\ExchangeRates\Tables\ExchangeRatesTable;
use App\Models\ExchangeRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'currency';

    public static function getModelLabel(): string
    {
        return 'Tasa de Cambio';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tasas de Cambio';
    }

    public static function getNavigationLabel(): string
    {
        return 'Tasas de Cambio';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Finanzas & Contabilidad';
    }

    public static function form(Schema $schema): Schema
    {
        return ExchangeRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExchangeRatesTable::configure($table);
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
            'index' => ListExchangeRates::route('/'),
            'create' => CreateExchangeRate::route('/create'),
            'edit' => EditExchangeRate::route('/{record}/edit'),
        ];
    }
}
