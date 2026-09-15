<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryMovements\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Movimiento')
                    ->schema([
                        Select::make('product_id')
                            ->label('Producto')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $product = Product::find($state);
                                    if ($product) {
                                        $set('unit_cost', $product->cost ?? 0);
                                    }
                                }
                            }),

                        Select::make('type')
                            ->label('Tipo de Movimiento')
                            ->options([
                                'in' => 'Entrada (Compra / Ingreso)',
                                'out' => 'Salida (Merma / Retiro)',
                                'adjustment' => 'Ajuste de Inventario',
                            ])
                            ->default('in')
                            ->required(),

                        TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->minValue(0.01)
                            ->default(1)
                            ->required(),

                        TextInput::make('unit_cost')
                            ->label('Costo Unitario')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->required(),

                        TextInput::make('concept')
                            ->label('Concepto / Descripción')
                            ->placeholder('Ej: Compra de inventario, Mercancía dañada, Conteo físico')
                            ->default('Movimiento manual de inventario')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
