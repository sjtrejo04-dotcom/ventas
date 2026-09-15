<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExchangeRates\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExchangeRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información de la Tasa de Cambio')
                    ->schema([
                        Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'USD' => 'Dólar Estadounidense (USD)',
                                'EUR' => 'Euro (EUR)',
                                'COP' => 'Peso Colombiano (COP)',
                            ])
                            ->default('USD')
                            ->required(),
                        TextInput::make('rate')
                            ->label('Tasa Oficial (Bs por unidad)')
                            ->placeholder('Ej. 36.500000')
                            ->required()
                            ->numeric()
                            ->minValue(0.000001)
                            ->step('0.000001')
                            ->helperText('Monto equivalente en Bolívares (VES).'),
                        DateTimePicker::make('date_published')
                            ->label('Fecha y Hora de Publicación')
                            ->default(now())
                            ->required(),
                    ])->columns(3),
            ]);
    }
}
