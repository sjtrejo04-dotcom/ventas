<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\PosTerminal;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // 1. Seed standard payment methods
    PaymentMethod::firstOrCreate(['name' => 'Efectivo (Bs)'], ['requires_reference' => false, 'applies_igtf' => false]);
    PaymentMethod::firstOrCreate(['name' => 'Efectivo Divisas ($)'], ['requires_reference' => false, 'applies_igtf' => true]);
    PaymentMethod::firstOrCreate(['name' => 'Punto de Venta'], ['requires_reference' => true, 'applies_igtf' => false]);
    PaymentMethod::firstOrCreate(['name' => 'Pago Móvil'], ['requires_reference' => true, 'applies_igtf' => false]);
    PaymentMethod::firstOrCreate(['name' => 'Cashea'], ['requires_reference' => true, 'applies_igtf' => false]);

    // 2. Official BCV Rate: 50.00 Bs/USD
    ExchangeRate::create([
        'currency' => 'USD',
        'rate' => 50.00,
        'date_published' => now(),
    ]);

    // 3. Default Cash Register: CAJA-01
    $this->register = CashRegister::firstOrCreate(
        ['code' => 'CAJA-01'],
        ['name' => 'Caja 01 Principal', 'is_active' => true]
    );

    // 4. Default Cashiers
    $this->cashier1 = User::factory()->create(['name' => 'Santiago Pérez (Cajero 1)']);
    $this->cashier2 = User::factory()->create(['name' => 'María Rodríguez (Cajera 2)']);

    // 5. Default Consumidor Final Customer
    $this->defaultCustomer = Customer::firstOrCreate(
        ['document_number' => '00000000'],
        [
            'document_type' => 'V',
            'name' => 'Consumidor Final',
            'address' => 'Ciudad',
            'phone' => '0000000000',
        ]
    );
});

/*
|--------------------------------------------------------------------------
| ESCENARIO 1: PAGO 100% BOLÍVARES (SIN IGTF)
|--------------------------------------------------------------------------
| Venta con pago exclusivo en Bolívares (Tarjeta de Débito y/o Cashea).
| Aserciones:
| - total_igtf debe ser exactamente 0.00
| - total_amount debe ser total_base + total_vat
| - no debe existir base imponible de IGTF (igtf_base = 0.00)
*/

test('scenario 1 - sale paid 100 percent in bolivares debit card has zero igtf and total equals base plus vat', function () {
    $this->actingAs($this->cashier1);

    // Open active shift for cashier 1
    $shift = CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->cashier1->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 500.00,
        'opening_cash_usd' => 50.00,
        'system_cash_bs' => 500.00,
        'system_cash_usd' => 50.00,
    ]);

    // Taxable product: Smart TV 50" -> $300.00 USD + 16% VAT = $348.00 USD
    // At rate 50 Bs/USD = 17,400.00 Bs
    $tv = Product::create([
        'name' => 'Smart TV 50" UHD 4K',
        'price' => 300.00,
        'stock' => 10,
        'cost' => 200.00,
        'has_vat' => true,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $tv->id)
        ->call('openPaymentModal')
        ->assertSet('showPaymentModal', true)
        // Pay 100% in Bolívares via Punto de Venta (Tarjeta de Débito)
        ->set('selectedPaymentMethod', 'pos_bs')
        ->set('paymentAmount', 17400.00)
        ->set('paymentReference', 'REF-DEBIT-778899')
        ->call('addPayment')
        // Verify fiscal breakdown before processing
        ->assertSet('fiscalSummary.subtotal', 300.00)
        ->assertSet('fiscalSummary.taxable_base', 300.00)
        ->assertSet('fiscalSummary.vat_amount', 48.00)
        ->assertSet('fiscalSummary.igtf_base', 0.00)
        ->assertSet('fiscalSummary.igtf_amount', 0.00)
        ->assertSet('fiscalSummary.total_amount_usd', 348.00)
        ->assertSet('fiscalSummary.total_amount_bs', 17400.00)
        ->assertSet('fiscalSummary.change_due_usd', 0.00)
        ->call('processSale')
        ->assertSet('showPaymentModal', false)
        ->assertSet('showTicketModal', true);

    // Database verification: Sale record
    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_base)->toBe(300.00)
        ->and((float) $sale->total_vat)->toBe(48.00)
        ->and((float) $sale->total_igtf)->toBe(0.00)
        ->and((float) $sale->total_amount)->toBe(348.00)
        ->and((float) $sale->total_amount)->toBe((float) $sale->total_base + (float) $sale->total_vat)
        ->and($sale->cash_shift_id)->toBe($shift->id)
        ->and($sale->user_id)->toBe($this->cashier1->id);

    // Database verification: Payment record
    $payment = Payment::where('sale_id', $sale->id)->first();
    expect($payment)->not->toBeNull()
        ->and((float) $payment->amount)->toBe(17400.00)
        ->and($payment->reference_number)->toBe('REF-DEBIT-778899')
        ->and($payment->paymentMethod->name)->toBe('Punto de Venta')
        ->and($payment->paymentMethod->applies_igtf)->toBeFalse();
});

test('scenario 1 - sale split 100 percent in bolivares between debit card and cashea has zero igtf', function () {
    $this->actingAs($this->cashier1);

    CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->cashier1->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 1000.00,
        'opening_cash_usd' => 100.00,
    ]);

    // Appliance: Lavadora Carga Frontal -> $400.00 USD + 16% VAT ($64.00) = $464.00 USD
    // Total in Bs = 23,200.00 Bs
    $washer = Product::create([
        'name' => 'Lavadora Carga Frontal 12kg',
        'price' => 400.00,
        'stock' => 5,
        'cost' => 280.00,
        'has_vat' => true,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $washer->id)
        ->call('openPaymentModal')
        // Payment 1: 50% via Punto de Venta (11,600.00 Bs)
        ->set('selectedPaymentMethod', 'pos_bs')
        ->set('paymentAmount', 11600.00)
        ->set('paymentReference', 'POS-00123')
        ->call('addPayment')
        // Payment 2: 50% via Cashea (11,600.00 Bs)
        ->set('selectedPaymentMethod', 'cashea')
        ->set('paymentAmount', 11600.00)
        ->set('paymentReference', 'CASHEA-APP-9988')
        ->call('addPayment')
        // Check fiscal calculations: 0 IGTF despite split financing
        ->assertSet('fiscalSummary.igtf_base', 0.00)
        ->assertSet('fiscalSummary.igtf_amount', 0.00)
        ->assertSet('fiscalSummary.total_amount_usd', 464.00)
        ->assertSet('fiscalSummary.total_amount_bs', 23200.00)
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_base)->toBe(400.00)
        ->and((float) $sale->total_vat)->toBe(64.00)
        ->and((float) $sale->total_igtf)->toBe(0.00)
        ->and((float) $sale->total_amount)->toBe(464.00)
        ->and((float) $sale->total_amount)->toBe((float) $sale->total_base + (float) $sale->total_vat);

    // Verify both payments exist and neither applied IGTF
    $payments = Payment::where('sale_id', $sale->id)->get();
    expect($payments)->toHaveCount(2);

    $totalPaidBs = $payments->sum('amount');
    expect((float) $totalPaidBs)->toBe(23200.00);
});

/*
|--------------------------------------------------------------------------
| ESCENARIO 2: CASO PLANSUÁREZ - PAGO CON EFECTIVO DIVISAS USD
|--------------------------------------------------------------------------
| Venta donde una parte o la totalidad se paga en Efectivo Divisas USD.
| Aserciones:
| - El sistema calcula exactamente el 3% de IGTF sobre el monto en divisas pagado
| - total_igtf > 0
| - total de la venta incluye total_base + total_vat + total_igtf
*/

test('scenario 2 - sale with 100 percent cash usd payment calculates 3 percent igtf dynamically and change due', function () {
    $this->actingAs($this->cashier1);

    CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->cashier1->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 500.00,
        'opening_cash_usd' => 50.00,
    ]);

    // Supermarket basket: Canasta Familiar $150.00 + 16% VAT ($24.00) = $174.00 USD base
    $basket = Product::create([
        'name' => 'Canasta de Víveres Familiar',
        'price' => 150.00,
        'stock' => 20,
        'cost' => 105.00,
        'has_vat' => true,
    ]);

    // Customer pays with a $200.00 USD cash bill
    // Rule: IVA is full 16% ($24.00). Cash USD adds 3% IGTF on $174.00 = $5.22 USD
    // Total sale = $150.00 + $24.00 + $5.22 = $179.22 USD
    // Change due = $200.00 - $179.22 = $20.78 USD = 1,039.00 Bs (at rate 50)
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $basket->id)
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'cash_usd')
        ->set('paymentAmount', 200.00)
        ->call('addPayment')
        // Livewire fiscal assertions
        ->assertSet('fiscalSummary.subtotal', 150.00)
        ->assertSet('fiscalSummary.taxable_base', 150.00)
        ->assertSet('fiscalSummary.vat_amount', 24.00)
        ->assertSet('fiscalSummary.igtf_base', 174.00)
        ->assertSet('fiscalSummary.igtf_amount', 5.22)
        ->assertSet('fiscalSummary.total_amount_usd', 179.22)
        ->assertSet('fiscalSummary.change_due_usd', 20.78)
        ->assertSet('fiscalSummary.change_due_bs', 1039.00)
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_base)->toBe(150.00)
        ->and((float) $sale->total_vat)->toBe(24.00)
        ->and((float) $sale->total_igtf)->toBe(5.22)
        ->and((float) $sale->total_igtf)->toBeGreaterThan(0.00)
        ->and((float) $sale->total_amount)->toBe(179.22)
        ->and((float) $sale->total_amount)->toBe(
            round((float) $sale->total_base + (float) $sale->total_vat + (float) $sale->total_igtf, 2)
        );
});

test('scenario 2 - mixed payment cash usd and bolivares debit card applies igtf strictly to usd cash portion', function () {
    $this->actingAs($this->cashier1);

    CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->cashier1->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 500.00,
        'opening_cash_usd' => 50.00,
    ]);

    // Gourmet grocery item: $100.00 (taxable) + 16% VAT = $16.00
    $item = Product::create([
        'name' => 'Combo Quesos y Embutidos Premium',
        'price' => 100.00,
        'stock' => 15,
        'cost' => 65.00,
        'has_vat' => true,
    ]);

    // Customer pays $50.00 in Cash USD.
    // IGTF 3% applies only to the $50 USD cash = $1.50 USD.
    // Full 16% VAT applies to the taxable base = $16.00 USD.
    // Total sale = $100.00 base + $16.00 VAT + $1.50 IGTF = $117.50 USD.
    // Remaining balance to pay = $117.50 - $50.00 = $67.50 USD.
    // In Bolívares (rate 50): 67.50 * 50 = 3,375.00 Bs via Punto de Venta.
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $item->id)
        ->call('openPaymentModal')
        // Payment 1: $50 USD cash
        ->set('selectedPaymentMethod', 'cash_usd')
        ->set('paymentAmount', 50.00)
        ->call('addPayment')
        // Payment 2: 3,375.00 Bs via Punto de Venta
        ->set('selectedPaymentMethod', 'pos_bs')
        ->set('paymentAmount', 3375.00)
        ->set('paymentReference', 'POS-SPLIT-4455')
        ->call('addPayment')
        // Verify fiscal calculations: IGTF is strictly 1.50 USD (3% of $50) and VAT is full 16.00 (16% of $100)
        ->assertSet('fiscalSummary.igtf_base', 50.00)
        ->assertSet('fiscalSummary.igtf_amount', 1.50)
        ->assertSet('fiscalSummary.vat_amount', 16.00)
        ->assertSet('fiscalSummary.total_amount_usd', 117.50)
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_base)->toBe(100.00)
        ->and((float) $sale->total_vat)->toBe(16.00)
        ->and((float) $sale->total_igtf)->toBe(1.50)
        ->and((float) $sale->total_igtf)->toBeGreaterThan(0.00)
        ->and((float) $sale->total_amount)->toBe(117.50)
        ->and((float) $sale->total_amount)->toBe(
            round((float) $sale->total_base + (float) $sale->total_vat + (float) $sale->total_igtf, 2)
        );

    // Verify two payment records in DB
    $payments = Payment::where('sale_id', $sale->id)->get();
    expect($payments)->toHaveCount(2);

    $usdPayment = $payments->firstWhere('payment_method_id', PaymentMethod::where('name', 'Efectivo Divisas ($)')->first()->id);
    expect($usdPayment)->not->toBeNull()
        ->and((float) $usdPayment->amount)->toBe(50.00);

    $bsPayment = $payments->firstWhere('payment_method_id', PaymentMethod::where('name', 'Punto de Venta')->first()->id);
    expect($bsPayment)->not->toBeNull()
        ->and((float) $bsPayment->amount)->toBe(3375.00);
});

/*
|--------------------------------------------------------------------------
| ESCENARIO 3: RELEVO DE TURNOS DE CAJEROS INDEPENDIENTES
|--------------------------------------------------------------------------
| El Cajero 1 abre turno en la Caja 01, realiza ventas, hace su arqueo ciego
| y cierra su turno. Inmediatamente el Cajero 2 inicia sesión, abre su propio
| turno en la misma Caja 01 con su propio fondo de caja, realiza ventas
| independientes y hace su propio cierre.
| Aserciones:
| - Cada turno (CashShift) tiene su propio user_id
| - Sus propios totales del sistema (system_cash_bs, system_cash_usd, etc.)
| - Sus propias diferencias auditadas (difference_cash_bs, difference_cash_usd)
| - Las ventas del Cajero 1 no se mezclan con las del Cajero 2
*/

test('scenario 3 - independent cashier shift handover on same register maintains complete shift, system totals, and sales segregation', function () {
    // Products for sales
    $product1 = Product::create([
        'name' => 'Café Gourmet 500g',
        'price' => 50.00,
        'stock' => 50,
        'cost' => 30.00,
        'has_vat' => true,
    ]);

    $product2 = Product::create([
        'name' => 'Harina de Trigo 1kg',
        'price' => 20.00,
        'stock' => 50,
        'cost' => 12.00,
        'has_vat' => false,
    ]);

    // -------------------------------------------------------------------------
    // FASE 1: CAJERO 1 OPERA EN CAJA 01
    // -------------------------------------------------------------------------
    $this->actingAs($this->cashier1);

    // Cajero 1 abre turno con 1,000 Bs y 100 USD
    Livewire::test(PosTerminal::class)
        ->set('selectedRegisterId', $this->register->id)
        ->set('openingCashBs', 1000.00)
        ->set('openingCashUsd', 100.00)
        ->call('openShift');

    $shift1 = CashShift::where('user_id', $this->cashier1->id)->where('status', 'open')->first();
    expect($shift1)->not->toBeNull()
        ->and($shift1->cash_register_id)->toBe($this->register->id);

    // Venta 1 de Cajero 1: Café $50 + 16% IVA ($8) = $58.00 USD pagado en Cash USD ($60 bill)
    // IGTF 3% on $58 = $1.74. Total = $59.74 USD. Paid $60. Change $0.26 USD.
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product1->id)
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'cash_usd')
        ->set('paymentAmount', 60.00)
        ->call('addPayment')
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    // Venta 2 de Cajero 1: Harina $20 (exenta) = 1,000 Bs (rate 50) pagado vía Punto de Venta
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product2->id)
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'pos_bs')
        ->set('paymentAmount', 1000.00)
        ->set('paymentReference', 'POS-C1-001')
        ->call('addPayment')
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    // Cajero 1 realiza arqueo ciego y cierra su turno:
    // Esperado en sistema:
    // - Cash Bs: 1000.00 (fondo inicial)
    // - Cash USD: 100.00 fondo + 60.00 venta = 160.00 USD
    // - POS Bs: 1000.00 Bs
    // Cajero 1 cuenta físicamente y declara:
    // - Cash Bs: 1025.00 (+25.00 Bs sobrante)
    // - Cash USD: 155.00 (-5.00 USD faltante)
    // - POS Bs: 1000.00 (0.00 exacto)
    Livewire::test(PosTerminal::class)
        ->call('openCloseShiftModal')
        ->set('declaredCashBs', 1025.00)
        ->set('declaredCashUsd', 155.00)
        ->set('declaredPosBs', 1000.00)
        ->set('shiftNotes', 'Entrega de turno a María Rodríguez')
        ->call('closeShift')
        ->assertSet('showShiftReportModal', true)
        ->assertSet('activeShiftId', null);

    $shift1->refresh();
    expect($shift1->status)->toBe('closed')
        ->and($shift1->closed_at)->not->toBeNull()
        ->and((float) $shift1->system_cash_bs)->toBe(1000.00)
        ->and((float) $shift1->system_cash_usd)->toBe(160.00)
        ->and((float) $shift1->system_pos_bs)->toBe(1000.00)
        ->and((float) $shift1->declared_cash_bs)->toBe(1025.00)
        ->and((float) $shift1->declared_cash_usd)->toBe(155.00)
        ->and((float) $shift1->difference_cash_bs)->toBe(25.00)
        ->and((float) $shift1->difference_cash_usd)->toBe(-5.00);

    // -------------------------------------------------------------------------
    // FASE 2: CAJERO 2 TOMA RELEVO EN LA MISMA CAJA 01
    // -------------------------------------------------------------------------
    $this->actingAs($this->cashier2);

    // Cajero 2 abre su propio turno en la misma Caja 01 con su propio fondo
    Livewire::test(PosTerminal::class)
        ->set('selectedRegisterId', $this->register->id)
        ->set('openingCashBs', 400.00)
        ->set('openingCashUsd', 30.00)
        ->call('openShift');

    $shift2 = CashShift::where('user_id', $this->cashier2->id)->where('status', 'open')->first();
    expect($shift2)->not->toBeNull()
        ->and($shift2->id)->not->toBe($shift1->id)
        ->and($shift2->cash_register_id)->toBe($this->register->id)
        ->and((float) $shift2->opening_cash_bs)->toBe(400.00)
        ->and((float) $shift2->opening_cash_usd)->toBe(30.00);

    // Venta 1 de Cajero 2: Harina $20 (exenta) = 1,000 Bs pagado vía Cashea
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product2->id)
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'cashea')
        ->set('paymentAmount', 1000.00)
        ->set('paymentReference', 'CASHEA-C2-8877')
        ->call('addPayment')
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    // Cajero 2 realiza arqueo ciego y cierra su turno:
    // Esperado en sistema:
    // - Cash Bs: 400.00 (fondo)
    // - Cash USD: 30.00 (fondo)
    // - Cashea Bs: 1000.00
    // Cajero 2 declara montos exactos:
    Livewire::test(PosTerminal::class)
        ->call('openCloseShiftModal')
        ->set('declaredCashBs', 400.00)
        ->set('declaredCashUsd', 30.00)
        ->set('declaredPosBs', 0.00)
        ->set('shiftNotes', 'Cierre de turno sin novedades')
        ->call('closeShift');

    $shift2->refresh();
    expect($shift2->status)->toBe('closed')
        ->and((float) $shift2->system_cash_bs)->toBe(400.00)
        ->and((float) $shift2->system_cash_usd)->toBe(30.00)
        ->and((float) $shift2->system_cashea_bs)->toBe(1000.00)
        ->and((float) $shift2->declared_cash_bs)->toBe(400.00)
        ->and((float) $shift2->declared_cash_usd)->toBe(30.00)
        ->and((float) $shift2->difference_cash_bs)->toBe(0.00)
        ->and((float) $shift2->difference_cash_usd)->toBe(0.00);

    // -------------------------------------------------------------------------
    // ASERCIONES DE INTEGRIDAD Y SEGREGACIÓN TOTAL
    // -------------------------------------------------------------------------
    // Turnos independientes
    expect($shift1->id)->not->toBe($shift2->id)
        ->and($shift1->user_id)->toBe($this->cashier1->id)
        ->and($shift2->user_id)->toBe($this->cashier2->id)
        ->and($shift1->cash_register_id)->toBe($this->register->id)
        ->and($shift2->cash_register_id)->toBe($this->register->id);

    // Ventas de Cajero 1 pertenecen exclusivamente al Turno 1
    $salesShift1 = Sale::where('cash_shift_id', $shift1->id)->get();
    expect($salesShift1)->toHaveCount(2);
    foreach ($salesShift1 as $sale) {
        expect($sale->user_id)->toBe($this->cashier1->id)
            ->and($sale->cash_shift_id)->toBe($shift1->id);
    }

    // Ventas de Cajero 2 pertenecen exclusivamente al Turno 2
    $salesShift2 = Sale::where('cash_shift_id', $shift2->id)->get();
    expect($salesShift2)->toHaveCount(1);
    foreach ($salesShift2 as $sale) {
        expect($sale->user_id)->toBe($this->cashier2->id)
            ->and($sale->cash_shift_id)->toBe($shift2->id);
    }

    // Ninguna venta de Cajero 1 está asignada al Turno 2 y viceversa
    $intersectingSales = Sale::where('cash_shift_id', $shift1->id)
        ->where('cash_shift_id', $shift2->id)
        ->count();
    expect($intersectingSales)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| ESCENARIO 4: KARDEX E INVENTARIO
|--------------------------------------------------------------------------
| Verificar que al registrar ventas en el POS, el stock de los productos
| se descuenta correctamente y se generan los registros correspondientes
| en inventory_movements.
*/

test('scenario 4 kardex - pos sales automatically deduct product stock and generate inventory movements', function () {
    $this->actingAs($this->cashier1);

    CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->cashier1->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 0,
        'opening_cash_usd' => 0,
    ]);

    // Product 1: Initial stock 100
    $p1 = Product::create([
        'name' => 'Arroz Extra Blanco 1kg',
        'price' => 1.50,
        'stock' => 100,
        'cost' => 0.90,
        'has_vat' => false,
    ]);

    // Product 2: Initial stock 50
    $p2 = Product::create([
        'name' => 'Pasta Larga 500g',
        'price' => 2.00,
        'stock' => 50,
        'cost' => 1.20,
        'has_vat' => true,
    ]);

    // POS Sale: 8 units of Product 1 and 5 units of Product 2
    Livewire::test(PosTerminal::class)
        ->call('addToCart', $p1->id)
        ->call('updateQuantity', $p1->id, 8.0)
        ->call('addToCart', $p2->id)
        ->call('updateQuantity', $p2->id, 5.0)
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'pos_bs')
        // Total: 8*1.50 = 12.00 exempt + 5*2.00 = 10.00 base + 1.60 VAT = 23.60 USD = 1,180.00 Bs
        ->set('paymentAmount', 1180.00)
        ->set('paymentReference', 'POS-KARDEX-01')
        ->call('addPayment')
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull();

    // Verify stock deductions
    expect((float) $p1->fresh()->stock)->toBe(92.00) // 100 - 8
        ->and((float) $p2->fresh()->stock)->toBe(45.00); // 50 - 5

    // Verify InventoryMovement records
    $movements = InventoryMovement::where('reference_id', $sale->id)->get();
    expect($movements)->toHaveCount(2);

    $mov1 = $movements->firstWhere('product_id', $p1->id);
    expect($mov1)->not->toBeNull()
        ->and($mov1->type)->toBe('out')
        ->and((float) $mov1->quantity)->toBe(8.00)
        ->and((float) $mov1->unit_cost)->toBe(0.90)
        ->and($mov1->concept)->toBe("Venta #{$sale->id}")
        ->and($mov1->reference_type)->toBe((new Sale)->getMorphClass())
        ->and($mov1->reference_id)->toBe($sale->id);

    $mov2 = $movements->firstWhere('product_id', $p2->id);
    expect($mov2)->not->toBeNull()
        ->and($mov2->type)->toBe('out')
        ->and((float) $mov2->quantity)->toBe(5.00)
        ->and((float) $mov2->unit_cost)->toBe(1.20)
        ->and($mov2->concept)->toBe("Venta #{$sale->id}")
        ->and($mov2->reference_type)->toBe((new Sale)->getMorphClass())
        ->and($mov2->reference_id)->toBe($sale->id);

    // Second consecutive sale with 4 more units of Product 1
    Livewire::test(PosTerminal::class)
        ->call('newSale')
        ->call('addToCart', $p1->id)
        ->call('updateQuantity', $p1->id, 4.0)
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'cash_bs')
        ->set('paymentAmount', 300.00) // 4 * 1.50 = 6 USD = 300 Bs
        ->call('addPayment')
        ->call('processSale');

    // Fresh stock should now be 92 - 4 = 88
    expect((float) $p1->fresh()->stock)->toBe(88.00);

    // Total out movements for Product 1 should sum to 12
    $totalOutP1 = InventoryMovement::where('product_id', $p1->id)
        ->where('type', 'out')
        ->sum('quantity');
    expect((float) $totalOutP1)->toBe(12.00);
});

/*
|--------------------------------------------------------------------------
| ESCENARIO 5: PRODUCTOS FRACCIONADOS / BALANZA Y GARANTÍAS
|--------------------------------------------------------------------------
| Venta con productos con cantidades decimales (ej. 1.375 kg) y productos
| con número de serie y días de garantía técnica.
*/

test('scenario 5 fractional and warranty - pos processes weighted products with decimal precision and tracked serial warranty items', function () {
    $this->actingAs($this->cashier1);

    CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->cashier1->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 0,
        'opening_cash_usd' => 0,
    ]);

    // 1. Fractional / Weight-based product: Queso Amarillo (Balanza)
    $cheese = Product::create([
        'name' => 'Queso Gouda Rebanado por Peso',
        'price' => 12.00, // $12.00 per kg
        'stock' => 30.000, // 30 kg in stock
        'cost' => 8.00,
        'has_vat' => false, // Exempt food
    ]);

    // 2. Hardware / Electronics product with serial number and technical warranty
    $drill = Product::create([
        'name' => 'Taladro Percutor Inalámbrico 20V',
        'price' => 110.00,
        'stock' => 8,
        'cost' => 70.00,
        'has_vat' => true,
    ]);

    Livewire::test(PosTerminal::class)
        // Add weighted product and set 1.375 kg
        ->call('addToCart', $cheese->id)
        ->call('updateQuantity', $cheese->id, 1.375)
        ->assertSet("cart.{$cheese->id}.quantity", 1.375)
        ->assertSet("cart.{$cheese->id}.subtotal", 16.50) // 1.375 * 12.00 = 16.50
        // Add electronic product and set serial + warranty
        ->call('addToCart', $drill->id)
        ->call('openItemSerialModal', $drill->id)
        ->assertSet('showItemSerialModal', true)
        ->assertSet('editingSerialProductId', $drill->id)
        ->set('itemSerialNumber', 'SN-DEWALT-2026-X9912')
        ->set('itemWarrantyDays', 180)
        ->call('saveItemSerial')
        ->assertSet('showItemSerialModal', false)
        ->assertSet("cart.{$drill->id}.serial_number", 'SN-DEWALT-2026-X9912')
        ->assertSet("cart.{$drill->id}.warranty_days", 180)
        // Open payment modal
        // Total:
        // Cheese: $16.50 (exempt)
        // Drill: $110.00 base + $17.60 VAT = $127.60
        // Total USD = 16.50 + 127.60 = 144.10 USD = 7,205.00 Bs
        ->call('openPaymentModal')
        ->set('selectedPaymentMethod', 'pos_bs')
        ->set('paymentAmount', 7205.00)
        ->set('paymentReference', 'POS-WARRANTY-123')
        ->call('addPayment')
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull();

    // Verify SaleItem for weighted product
    $cheeseItem = SaleItem::where('sale_id', $sale->id)
        ->where('product_id', $cheese->id)
        ->first();
    expect($cheeseItem)->not->toBeNull()
        ->and((float) $cheeseItem->quantity)->toBe(1.375)
        ->and((float) $cheeseItem->subtotal)->toBe(16.50);

    // Verify stock deduction with decimal precision: 30.000 - 1.375 = 28.625
    expect((float) $cheese->fresh()->stock)->toBe(28.625);

    // Verify InventoryMovement for weighted product
    $cheeseMov = InventoryMovement::where('product_id', $cheese->id)
        ->where('reference_id', $sale->id)
        ->first();
    expect($cheeseMov)->not->toBeNull()
        ->and($cheeseMov->type)->toBe('out')
        ->and((float) $cheeseMov->quantity)->toBe(1.375);

    // Verify SaleItem for serial/warranty product
    $drillItem = SaleItem::where('sale_id', $sale->id)
        ->where('product_id', $drill->id)
        ->first();
    expect($drillItem)->not->toBeNull()
        ->and((float) $drillItem->quantity)->toBe(1.0)
        ->and($drillItem->serial_number)->toBe('SN-DEWALT-2026-X9912')
        ->and($drillItem->warranty_days)->toBe(180);

    expect((float) $drill->fresh()->stock)->toBe(7.0);
});
