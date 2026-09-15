<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Método de Pago')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Método')
                            ->placeholder('Ej. Transferencia Bancaria, Pago Móvil, Efectivo USD')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('requires_reference')
                            ->label('Requiere Referencia')
                            ->helperText('Activar si este método exige comprobante o número de referencia.')
                            ->default(false),
                        Toggle::make('applies_igtf')
                            ->label('Aplica IGTF (3%)')
                            ->helperText('Activar si los pagos con este método están sujetos al IGTF.')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Disponible para nuevas transacciones.')
                            ->default(true),
                    ])->columns(3),
            ]);
    }
}
