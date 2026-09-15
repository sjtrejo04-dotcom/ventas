<?php

namespace App\Filament\Admin\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Cliente')
                    ->schema([
                        Grid::make(4)->schema([
                            Select::make('document_type')
                                ->label('Tipo')
                                ->options([
                                    'V' => 'Venezolano (V)',
                                    'J' => 'Jurídico (J)',
                                    'E' => 'Extranjero (E)',
                                    'G' => 'Gubernamental (G)',
                                ])
                                ->required()
                                ->columnSpan(1),
                            TextInput::make('document_number')
                                ->label('Documento / Cédula')
                                ->required()
                                ->maxLength(20)
                                ->columnSpan(3),
                        ])->columnSpanFull(),
                        TextInput::make('name')
                            ->label('Nombre / Razón Social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Información Adicional')
                    ->schema([
                        Textarea::make('address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
