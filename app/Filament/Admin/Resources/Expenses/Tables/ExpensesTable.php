<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('N° Factura')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider.name')
                    ->label('Proveedor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_date')
                    ->label('Fecha')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->label('Estado de Pago')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'paid' => 'De Contado',
                        'pending' => 'A Crédito',
                        default => $state ?? '—',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('total_base')
                    ->label('Base Imponible')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('total_vat')
                    ->label('IVA')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Factura')
                    ->money('USD')
                    ->sortable(),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                SelectFilter::make('payment_status')
                    ->label('Condición de Pago')
                    ->options([
                        'paid' => 'De Contado',
                        'pending' => 'A Crédito',
                    ]),
                SelectFilter::make('provider')
                    ->label('Proveedor')
                    ->relationship('provider', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
