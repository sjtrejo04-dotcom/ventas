<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ExchangeRates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExchangeRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('currency')
                    ->label('Moneda')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rate')
                    ->label('Tasa Oficial (VES)')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),
                TextColumn::make('date_published')
                    ->label('Fecha Publicada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date_published', 'desc')
            ->filters([
                SelectFilter::make('currency')
                    ->label('Moneda')
                    ->options([
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'COP' => 'COP',
                    ]),
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
