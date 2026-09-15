<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Schemas;

use App\Models\Product;
use App\Services\CommercialCalculationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Proveedor y Factura Fiscal')
                    ->schema([
                        Select::make('provider_id')
                            ->label('Proveedor / Distribuidor')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nombre / Razón Social')
                                    ->required(),
                                TextInput::make('rif')
                                    ->label('RIF / Documento')
                                    ->required(),
                            ]),
                        Select::make('user_id')
                            ->label('Registrado por')
                            ->relationship('user', 'name')
                            ->default(auth()->id())
                            ->required()
                            ->hidden(),
                        TextInput::make('invoice_number')
                            ->label('N° Factura Proveedor')
                            ->placeholder('Ej. 0001234')
                            ->required(),
                        TextInput::make('control_number')
                            ->label('N° Control Fiscal')
                            ->placeholder('Ej. 00-001234')
                            ->required(),
                        DatePicker::make('invoice_date')
                            ->label('Fecha de Factura')
                            ->default(now())
                            ->required(),
                        Radio::make('payment_status')
                            ->label('Condición de Pago')
                            ->options([
                                'paid' => 'De Contado',
                                'pending' => 'A Crédito',
                            ])
                            ->default('paid')
                            ->inline()
                            ->live(),
                        Select::make('payment_method_id')
                            ->label('Método de Pago')
                            ->relationship('paymentMethod', 'name')
                            ->visible(fn (Get $get): bool => $get('payment_status') === 'paid')
                            ->required(fn (Get $get): bool => $get('payment_status') === 'paid'),
                        DatePicker::make('due_date')
                            ->label('Fecha de Vencimiento')
                            ->visible(fn (Get $get): bool => $get('payment_status') === 'pending')
                            ->required(fn (Get $get): bool => $get('payment_status') === 'pending')
                            ->default(now()->addDays(15)),
                    ])->columns(3)->columnSpanFull(),

                Section::make('Renglones de Mercancía Recibida')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->minItems(1)
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto de Catálogo')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label('Nombre del Producto')
                                            ->required(),
                                        TextInput::make('description')
                                            ->label('Descripción'),
                                        Toggle::make('has_vat')
                                            ->label('¿Aplica IVA 16% (G)?')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $data['price'] = $data['price'] ?? 0.00;
                                        $data['cost'] = $data['cost'] ?? 0.00;
                                        $data['stock'] = $data['stock'] ?? 0;
                                        $product = Product::create($data);

                                        return $product->id;
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($product = Product::find($state)) {
                                            $set('has_vat', (bool) $product->has_vat);
                                            if (! $get('unit_cost') && (float) $product->cost > 0) {
                                                $set('unit_cost', (float) $product->cost);
                                            }
                                        }
                                        self::updateItemCalculations($get, $set);
                                    })
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 3,
                                    ]),

                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 1,
                                    ]),

                                TextInput::make('unit_cost')
                                    ->label('Costo Proveedor')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 2,
                                    ]),

                                TextInput::make('margin_percent')
                                    ->label('Margen')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(99.99)
                                    ->default(30.00)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 1,
                                    ]),

                                TextInput::make('selling_price')
                                    ->label('PVP Venta')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->prefix('$')
                                    ->helperText('PVP = Costo / (1 - Margen%)')
                                    ->live(onBlur: true)
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 2,
                                    ]),

                                Toggle::make('has_vat')
                                    ->label('IVA 16% (G)')
                                    ->default(true)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 1,
                                    ]),

                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 1,
                                    ]),

                                TextInput::make('vat_amount')
                                    ->label('Monto IVA')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->dehydrated()
                                    ->columnSpan([
                                        'default' => 6,
                                        'md' => 1,
                                    ]),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateInvoiceTotals($get, $set, isRoot: true);
                            }),
                    ])->columnSpanFull(),

                Section::make('Liquidación Fiscal de la Factura')
                    ->schema([
                        TextInput::make('total_base')
                            ->label('Base Imponible 16% (G)')
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->default(0),
                        TextInput::make('total_exempt')
                            ->label('Monto Exento (E)')
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->default(0),
                        TextInput::make('total_vat')
                            ->label('IVA 16% (Crédito Fiscal)')
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->default(0),
                        TextInput::make('total_amount')
                            ->label('Total Factura Proveedor')
                            ->prefix('$')
                            ->readOnly()
                            ->dehydrated()
                            ->default(0),
                    ])->columns(4)->columnSpanFull(),
            ]);
    }

    public static function updateItemCalculations(Get $get, Set $set, bool $updatePrice = true): void
    {
        $qty = (float) ($get('quantity') ?? 1);
        $cost = (float) ($get('unit_cost') ?? 0);
        $margin = (float) ($get('margin_percent') ?? 30);
        $hasVat = (bool) ($get('has_vat') ?? true);

        if ($updatePrice) {
            $sellingPrice = CommercialCalculationService::calculateSellingPrice($cost, $margin);
            $set('selling_price', $sellingPrice);
        }

        $subtotal = CommercialCalculationService::calculateItemSubtotal($qty, $cost);
        $vat = CommercialCalculationService::calculateItemVat($subtotal, $hasVat);

        $set('subtotal', $subtotal);
        $set('vat_amount', $vat);

        self::updateInvoiceTotals($get, $set, isRoot: false);
    }

    public static function updateInvoiceTotals(Get $get, Set $set, bool $isRoot = true): void
    {
        $items = $isRoot ? $get('items') : $get('../../items');
        if (! is_array($items)) {
            $items = [];
        }

        $totals = CommercialCalculationService::calculateInvoiceTotals($items);
        $setTarget = $isRoot ? '' : '../../';

        $set($setTarget.'total_base', $totals['total_base']);
        $set($setTarget.'total_exempt', $totals['total_exempt']);
        $set($setTarget.'total_vat', $totals['total_vat']);
        $set($setTarget.'total_amount', $totals['total_amount']);
    }
}
