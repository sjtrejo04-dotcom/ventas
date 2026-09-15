<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\InventoryMovements\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha del Movimiento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
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
                    })
                    ->searchable(),
                TextColumn::make('concept')
                    ->label('Concepto / Descripción')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stock_after_movement')
                    ->label('Saldo Restante')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_cost')
                    ->label('Costo Unitario')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Usuario (Trabajador)')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reference_type')
                    ->label('Documento')
                    ->formatStateUsing(function ($state, $record) {
                        if (! $state) {
                            return 'Manual';
                        }
                        $class = class_basename($state);

                        return $class.' #'.$record->reference_id;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->bulkActions([
                // Read-only
            ]);
    }
}
