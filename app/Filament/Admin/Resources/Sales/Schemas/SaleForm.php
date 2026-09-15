<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Schemas;

use App\Models\PaymentMethod;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registro de Venta')
                    ->schema([
                        // Fila 1: Datos Generales
                        Grid::make(12)
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('N° Factura Física')
                                    ->required()
                                    ->maxLength(8)
                                    ->regex('/^\d+$/')
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 3,
                                    ]),
                                DatePicker::make('invoice_date')
                                    ->label('Fecha de Venta')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 3,
                                    ]),
                                Select::make('customer_id')
                                    ->label('Cliente')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Grid::make(2)
                                            ->schema([
                                                // Columna Izquierda
                                                Group::make([
                                                    TextInput::make('name')
                                                        ->label('Nombre y Apellido')
                                                        ->required(),

                                                    Grid::make(12)
                                                        ->schema([
                                                            Select::make('document_type')
                                                                ->label('Tipo Doc.')
                                                                ->options([
                                                                    'V' => 'V',
                                                                    'E' => 'E',
                                                                    'J' => 'J',
                                                                    'G' => 'G',
                                                                    'P' => 'P',
                                                                ])
                                                                ->required()
                                                                ->columnSpan(4),

                                                            TextInput::make('document_number')
                                                                ->label('Documento de Identidad')
                                                                ->required()
                                                                ->maxLength(8)
                                                                ->regex('/^\d+$/')
                                                                ->columnSpan(8),
                                                        ]),
                                                ])->columnSpan(1),

                                                // Columna Derecha
                                                Group::make([
                                                    TextInput::make('phone')
                                                        ->label('Teléfono')
                                                        ->tel()
                                                        ->mask('+58-9999999999')
                                                        ->placeholder('+58-0000000000'),

                                                    TextInput::make('address')
                                                        ->label('Dirección corta'),
                                                ])->columnSpan(1),
                                            ]),
                                    ])
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 4,
                                    ]),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'pending' => 'Pendiente',
                                        'paid' => 'Pagado',
                                        'cancelled' => 'Cancelado',
                                    ])
                                    ->default('paid')
                                    ->required()
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 2,
                                    ]),
                                Select::make('user_id')
                                    ->label('Vendedor/Cajero')
                                    ->relationship('user', 'name')
                                    ->default(auth()->id())
                                    ->required()
                                    ->hidden()
                                    ->columnSpan(12),
                            ]),

                        // Separador
                        Placeholder::make('divider1')
                            ->hiddenLabel()
                            ->content(new HtmlString('<hr class="border-gray-200 dark:border-gray-700">'))
                            ->columnSpan('full'),

                        // Fila 2: Artículos (Repeater)
                        Repeater::make('saleItems')
                            ->label('Artículos de la Venta')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->noSearchResultsMessage('Producto no encontrado. Debe registrar la Factura de Compra primero.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $product = Product::find($state);
                                        $price = $product?->price ?? 0;
                                        $cost = $product?->cost ?? 0;
                                        $set('unit_price', $price);
                                        $set('unit_cost', $cost);
                                        $quantity = $get('quantity') ?? 1;
                                        $sub = round($price * $quantity, 2);
                                        $set('subtotal', $sub);
                                        $set('vat_amount', $product && $product->has_vat ? round($sub * (float) config('app.vat_rate', 0.16), 2) : 0);
                                        self::updateTotals($get, $set);
                                    })
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 4,
                                    ]),
                                TextInput::make('quantity')
                                    ->label('Cant.')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(fn (Get $get) => Product::find($get('product_id'))?->stock ?? 1)
                                    ->hint(fn (Get $get) => 'Inventario: '.(Product::find($get('product_id'))?->stock ?? 0))
                                    ->hintColor('success')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $price = $get('unit_price') ?? 0;
                                        $sub = round((float) $state * (float) $price, 2);
                                        $set('subtotal', $sub);
                                        $product = Product::find($get('product_id'));
                                        $set('vat_amount', $product && $product->has_vat ? round($sub * (float) config('app.vat_rate', 0.16), 2) : 0);
                                        self::updateTotals($get, $set);
                                    })
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 2,
                                    ]),
                                TextInput::make('unit_price')
                                    ->label('Precio Unit.')
                                    ->numeric()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $sub = round((float) $state * (float) $quantity, 2);
                                        $set('subtotal', $sub);
                                        $product = Product::find($get('product_id'));
                                        $set('vat_amount', $product && $product->has_vat ? round($sub * (float) config('app.vat_rate', 0.16), 2) : 0);
                                        self::updateTotals($get, $set);
                                    })
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 2,
                                    ]),
                                TextInput::make('vat_amount')
                                    ->label('I.V.A.')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 2,
                                    ]),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
                                    ->extraInputAttributes(['class' => 'text-green-600 dark:text-green-400 font-bold'])
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 2,
                                    ]),
                                Hidden::make('unit_cost')->default(0),
                            ])
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set, true);
                            })
                            ->columns(12)
                            ->defaultItems(1)
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $productQuantities = [];

                                    if (! is_array($value)) {
                                        return;
                                    }

                                    foreach ($value as $item) {
                                        $productId = $item['product_id'] ?? null;
                                        $quantity = (int) ($item['quantity'] ?? 0);

                                        if ($productId && $quantity > 0) {
                                            $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + $quantity;
                                        }
                                    }

                                    foreach ($productQuantities as $productId => $totalQuantity) {
                                        $product = Product::find($productId);
                                        if ($product && $totalQuantity > $product->stock) {
                                            $fail("La cantidad total del producto '{$product->name}' ({$totalQuantity}) supera el inventario disponible ({$product->stock}).");
                                        }
                                    }
                                };
                            })
                            ->columnSpan('full'),

                        // Separador
                        Placeholder::make('divider2')
                            ->hiddenLabel()
                            ->content(new HtmlString('<hr class="border-gray-200 dark:border-gray-700">'))
                            ->columnSpan('full'),

                        // Fila 3: Pagos y Totales (Split)
                        Grid::make(12)
                            ->schema([
                                // Sección de Pagos
                                Group::make()
                                    ->schema([
                                        Repeater::make('payments')
                                            ->label('Registro de Pagos')
                                            ->relationship()
                                            ->schema([
                                                Select::make('payment_method_id')
                                                    ->label('Método')
                                                    ->relationship('paymentMethod', 'name')
                                                    ->required()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                                        self::calculatePaymentAmount($get, $set);
                                                    })
                                                    ->columnSpan(4),
                                                TextInput::make('base_amount')
                                                    ->label('Monto a Abonar ($)')
                                                    ->suffixAction(
                                                        Action::make('pay_remaining')
                                                            ->icon('heroicon-m-banknotes')
                                                            ->tooltip('Completar restante')
                                                            ->action(function (Set $set, Get $get) {
                                                                $pending = (float) $get('../../pending_amount');
                                                                if ($pending > 0) {
                                                                    $set('base_amount', $pending);
                                                                    self::calculatePaymentAmount($get, $set);
                                                                }
                                                            })
                                                    )
                                                    ->numeric()
                                                    ->required()
                                                    ->live(onBlur: true)
                                                    ->dehydrated(false)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                                        self::calculatePaymentAmount($get, $set);
                                                    })
                                                    ->columnSpan(4),
                                                TextInput::make('amount')
                                                    ->label('Cobro Calculado')
                                                    ->numeric()
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->columnSpan(4),
                                                TextInput::make('reference_number')
                                                    ->label('Referencia')
                                                    ->required(fn (Get $get) => PaymentMethod::find($get('payment_method_id'))?->requires_reference ?? false)
                                                    ->visible(fn (Get $get) => PaymentMethod::find($get('payment_method_id'))?->requires_reference ?? false)
                                                    ->columnSpan(4),
                                            ])
                                            ->columns(12)
                                            ->defaultItems(1),
                                    ])
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 8,
                                    ]),

                                // Sección de Totales
                                Group::make()
                                    ->schema([
                                        Section::make('Resumen')
                                            ->schema([
                                                TextInput::make('total_exempt')
                                                    ->label('Monto Exento')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated(),
                                                TextInput::make('total_base')
                                                    ->label('Base Imponible')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated(),
                                                TextInput::make('total_vat')
                                                    ->label('I.V.A. (Generado)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated(),
                                                TextInput::make('total_igtf')
                                                    ->label('I.G.T.F. (Cobrado)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated(),
                                                TextInput::make('pending_amount')
                                                    ->label('Deuda Pendiente ($)')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->disabled()
                                                    ->dehydrated(false)
                                                    ->extraInputAttributes(['class' => 'font-bold text-danger-600']),
                                                TextInput::make('total_amount')
                                                    ->label('Total a Pagar')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->required()
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->extraInputAttributes(['class' => 'text-xl font-bold text-primary-600']),
                                            ]),
                                    ])
                                    ->columnSpan([
                                        'default' => 12,
                                        'md' => 4,
                                    ]),
                            ]),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    public static function updateTotals(Get $get, Set $set, bool $isRoot = false): void
    {
        $items = $isRoot ? $get('saleItems') : $get('../../saleItems');
        $payments = $isRoot ? $get('payments') : $get('../../payments');

        $totalExempt = 0.0;
        $totalBase = 0.0;
        $totalVatGen = 0.0;

        if (is_array($items)) {
            foreach ($items as $item) {
                $sub = (float) ($item['subtotal'] ?? 0);
                $vat = (float) ($item['vat_amount'] ?? 0);

                if ($vat > 0) {
                    $totalBase += $sub;
                } else {
                    $totalExempt += $sub;
                }

                $totalVatGen += $vat;
            }
        }

        $paidAmount = 0.0;
        $totalIgtf = 0.0;

        if (is_array($payments)) {
            foreach ($payments as $payment) {
                $base = (float) ($payment['base_amount'] ?? 0);
                $paidAmount += $base;

                $methodId = $payment['payment_method_id'] ?? null;
                if ($methodId) {
                    $method = PaymentMethod::find($methodId);
                    if ($method && $method->applies_igtf) {
                        $totalIgtf += round($base * 0.03, 2);
                    }
                }
            }
        }

        $invoiceTotal = $totalExempt + $totalBase + $totalVatGen;
        $totalAmount = $invoiceTotal + $totalIgtf;
        $pendingAmount = $totalAmount - $paidAmount;

        $setTarget = $isRoot ? '' : '../../';

        $set($setTarget.'total_exempt', number_format($totalExempt, 2, '.', ''));
        $set($setTarget.'total_base', number_format($totalBase, 2, '.', ''));
        $set($setTarget.'total_vat', number_format($totalVatGen, 2, '.', ''));
        $set($setTarget.'total_igtf', number_format($totalIgtf, 2, '.', ''));
        $set($setTarget.'total_amount', number_format($totalAmount, 2, '.', ''));
        $set($setTarget.'pending_amount', number_format($pendingAmount, 2, '.', ''));
    }

    public static function calculatePaymentAmount(Get $get, Set $set): void
    {
        $base = (float) ($get('base_amount') ?? 0);
        $methodId = $get('payment_method_id');

        if (! $methodId || $base <= 0) {
            $set('amount', 0);

            return;
        }

        $method = PaymentMethod::find($methodId);
        if (! $method) {
            $set('amount', 0);

            return;
        }

        if ($method->applies_igtf) {
            // El IGTF se suma al total en updateTotals. El cobro físico es igual al abono.
            $set('amount', round($base, 2));
        } else {
            // El abono ya incluye IVA de la factura, por lo que solo usamos la tasa (800 Bs)
            $set('amount', round($base * 800, 2));
        }

        self::updateTotals($get, $set, false);
    }
}
