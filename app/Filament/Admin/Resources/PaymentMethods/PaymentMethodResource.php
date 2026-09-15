<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PaymentMethods;

use App\Filament\Admin\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Admin\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Admin\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Admin\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use App\Filament\Admin\Resources\PaymentMethods\Tables\PaymentMethodsTable;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return 'Método de Pago';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Métodos de Pago';
    }

    public static function getNavigationLabel(): string
    {
        return 'Métodos de Pago';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Finanzas & Contabilidad';
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodsTable::configure($table);
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
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }
}
