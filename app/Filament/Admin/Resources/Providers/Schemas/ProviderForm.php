<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Providers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Proveedor')
                    ->schema([
                        TextInput::make('name')
                            ->label('Razón Social / Nombre')
                            ->placeholder('Ej. Distribuidora Central C.A.')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('rif')
                            ->label('RIF / Documento')
                            ->placeholder('Ej. J-12345678-9')
                            ->required()
                            ->maxLength(20)
                            ->unique(ignoreRecord: true),
                        TextInput::make('phone')
                            ->label('Teléfono de Contacto')
                            ->placeholder('Ej. +58 412 1234567')
                            ->tel()
                            ->maxLength(50),
                        Textarea::make('address')
                            ->label('Dirección Fiscal')
                            ->placeholder('Dirección completa del proveedor...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
