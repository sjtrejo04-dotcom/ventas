<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Producto')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                    ])->columns(1),

                Section::make('Precios y Configuración')
                    ->schema([
                        TextInput::make('cost')
                            ->label('Costo')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('$'),
                        TextInput::make('price')
                            ->label('Precio de Venta')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
                        TextInput::make('stock')
                            ->label('Stock / Inventario')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Toggle::make('has_vat')
                            ->label('Aplica IVA (16%)')
                            ->default(true)
                            ->required(),
                    ])->columns(3),
            ]);
    }
}
