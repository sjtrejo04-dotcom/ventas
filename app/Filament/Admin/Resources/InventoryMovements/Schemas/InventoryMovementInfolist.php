<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Movimiento')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Fecha del Movimiento')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('product.name')
                            ->label('Producto'),
                        TextEntry::make('type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'in' => 'success',
                                'out' => 'danger',
                                'adjustment' => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'in' => 'Entrada',
                                'out' => 'Salida',
                                'adjustment' => 'Ajuste',
                                default => $state,
                            }),
                        TextEntry::make('concept')
                            ->label('Concepto / Descripción'),
                        TextEntry::make('quantity')
                            ->label('Cantidad')
                            ->numeric(),
                        TextEntry::make('stock_after_movement')
                            ->label('Stock Resultante')
                            ->numeric(),
                        TextEntry::make('unit_cost')
                            ->label('Costo Unitario')
                            ->money('USD'),
                        TextEntry::make('user.name')
                            ->label('Registrado por')
                            ->placeholder('Sistema / Automático'),
                    ])->columns(2),
            ]);
    }
}
