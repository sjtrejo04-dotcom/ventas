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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed default payment methods
    PaymentMethod::firstOrCreate(['name' => 'Efectivo (Bs)'], ['requires_reference' => false, 'applies_igtf' => false]);
    PaymentMethod::firstOrCreate(['name' => 'Efectivo Divisas ($)'], ['requires_reference' => false, 'applies_igtf' => true]);
    PaymentMethod::firstOrCreate(['name' => 'Punto de Venta'], ['requires_reference' => true, 'applies_igtf' => false]);
    PaymentMethod::firstOrCreate(['name' => 'Pago Móvil'], ['requires_reference' => true, 'applies_igtf' => false]);
    PaymentMethod::firstOrCreate(['name' => 'Cashea'], ['requires_reference' => true, 'applies_igtf' => false]);

    // Active BCV Rate: 50 Bs/USD
    ExchangeRate::create([
        'currency' => 'USD',
        'rate' => 50.00,
        'date_published' => now(),
    ]);

    // Active Register
    $this->register = CashRegister::firstOrCreate(
        ['code' => 'CAJA-01'],
        ['name' => 'Caja 01 Principal', 'is_active' => true]
    );

    // Default User
    $this->user = User::factory()->create(['name' => 'Cajero Test']);
});

test('pos terminal page renders correctly for authenticated cashier', function () {
    $this->actingAs($this->user)
        ->get(route('filament.admin.pages.pos'))
        ->assertSuccessful()
        ->assertSee('Terminal POS')
        ->assertSee('F2')
        ->assertSee('F3')
        ->assertSee('F4');
});

test('pos terminal blocks sales and requires shift opening if cashier has no open shift', function () {
    $this->actingAs($this->user);

    $product = Product::create([
        'name' => 'Harina de Trigo 1kg',
        'price' => 1.50,
        'stock' => 20,
        'cost' => 1.00,
        'has_vat' => false,
    ]);

    Livewire::test(PosTerminal::class)
        ->assertSet('activeShiftId', null)
        ->assertSet('showShiftOpenModal', true)
        ->call('addToCart', $product->id)
        ->assertSet('cart', [])
        ->assertSet('showShiftOpenModal', true);
});

test('cashier can open cash shift successfully from pos terminal', function () {
    $this->actingAs($this->user);

    Livewire::test(PosTerminal::class)
        ->set('selectedRegisterId', $this->register->id)
        ->set('openingCashBs', 500.00)
        ->set('openingCashUsd', 50.00)
        ->call('openShift')
        ->assertSet('showShiftOpenModal', false)
        ->assertNotSet('activeShiftId', null);

    $shift = CashShift::where('user_id', $this->user->id)->where('status', 'open')->first();
    expect($shift)->not->toBeNull()
        ->and((float) $shift->opening_cash_bs)->toBe(500.00)
        ->and((float) $shift->opening_cash_usd)->toBe(50.00);
});

test('cashier can add products to cart, increment, decrement and update quantity', function () {
    $this->actingAs($this->user);

    // Open shift first
    $shift = CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 100.00,
        'opening_cash_usd' => 10.00,
        'system_cash_bs' => 100.00,
        'system_cash_usd' => 10.00,
    ]);

    $p1 = Product::create([
        'name' => 'Aceite de Girasol 1L',
        'price' => 3.00,
        'stock' => 15,
        'cost' => 2.00,
        'has_vat' => true,
    ]);

    $p2 = Product::create([
        'name' => 'Arroz Blanco 1kg',
        'price' => 1.20,
        'stock' => 30,
        'cost' => 0.80,
        'has_vat' => false,
    ]);

    Livewire::test(PosTerminal::class)
        ->assertSet('activeShiftId', $shift->id)
        ->call('addToCart', $p1->id)
        ->assertCount('cart', 1)
        ->call('incrementQuantity', $p1->id)
        ->assertSet("cart.{$p1->id}.quantity", 2.0)
        ->assertSet("cart.{$p1->id}.subtotal", 6.00)
        ->call('decrementQuantity', $p1->id)
        ->assertSet("cart.{$p1->id}.quantity", 1.0)
        ->assertSet("cart.{$p1->id}.subtotal", 3.00)
        ->call('addToCart', $p2->id)
        ->assertCount('cart', 2)
        ->call('removeFromCart', $p1->id)
        ->assertCount('cart', 1)
        ->call('clearCart')
        ->assertCount('cart', 0);
});

test('cashier can set serial number and warranty on an item', function () {
    $this->actingAs($this->user);

    CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 0,
        'opening_cash_usd' => 0,
    ]);

    $tv = Product::create([
        'name' => 'Smart TV 43"',
        'price' => 250.00,
        'stock' => 5,
        'cost' => 180.00,
        'has_vat' => true,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $tv->id)
        ->call('openItemSerialModal', $tv->id)
        ->assertSet('showItemSerialModal', true)
        ->assertSet('editingSerialProductId', $tv->id)
        ->set('itemSerialNumber', 'SN-TV43-987654')
        ->set('itemWarrantyDays', 90)
        ->call('saveItemSerial')
        ->assertSet('showItemSerialModal', false)
        ->assertSet("cart.{$tv->id}.serial_number", 'SN-TV43-987654')
        ->assertSet("cart.{$tv->id}.warranty_days", 90);
});

test('cashier can express register a customer with rif and assign to sale', function () {
    $this->actingAs($this->user);

    Livewire::test(PosTerminal::class)
        ->call('openCustomerModal')
        ->assertSet('showCustomerModal', true)
        ->set('customerDocType', 'J')
        ->set('customerDocNumber', '312345678')
        ->set('customerName', 'Inversiones El Éxito C.A.')
        ->set('customerPhone', '04141234567')
        ->set('customerAddress', 'Las Mercedes, Caracas')
        ->call('saveCustomer')
        ->assertSet('showCustomerModal', false);

    $customer = Customer::where('document_number', '312345678')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->name)->toBe('Inversiones El Éxito C.A.');
});

test('single currency bolivares payment has 0 igtf and completes sale', function () {
    $this->actingAs($this->user);

    $shift = CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 0,
        'opening_cash_usd' => 0,
    ]);

    // Product $100 + 16% VAT = $116 USD = 5,800 Bs (at rate 50)
    $product = Product::create([
        'name' => 'Licuadora Doméstica 500W',
        'price' => 100.00,
        'stock' => 10,
        'cost' => 60.00,
        'has_vat' => true,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        ->assertSet('showPaymentModal', true)
        // Add payment: 100% in Bolívares via Punto de Venta (5,800 Bs)
        ->set('selectedPaymentMethod', 'pos_bs')
        ->set('paymentAmount', 5800.00)
        ->set('paymentReference', 'LOTE-7890')
        ->call('addPayment')
        // Check fiscal calculations: 0 IGTF
        ->assertSet('fiscalSummary.igtf_amount', 0.00)
        ->assertSet('fiscalSummary.total_amount_usd', 116.00)
        ->assertSet('fiscalSummary.total_amount_bs', 5800.00)
        ->call('processSale')
        ->assertSet('showPaymentModal', false)
        ->assertSet('showTicketModal', true)
        ->assertNotSet('ticketData', []);

    // Verify database records
    $sale = Sale::latest('id')->first();
    expect($sale)->not->toBeNull()
        ->and((float) $sale->total_base)->toBe(100.00)
        ->and((float) $sale->total_vat)->toBe(16.00)
        ->and((float) $sale->total_igtf)->toBe(0.00)
        ->and((float) $sale->total_amount)->toBe(116.00)
        ->and($sale->cash_shift_id)->toBe($shift->id);

    // Verify stock decremented
    expect($product->fresh()->stock)->toBe(9);

    // Verify inventory movement
    $mov = InventoryMovement::where('product_id', $product->id)->first();
    expect($mov)->not->toBeNull()
        ->and($mov->type)->toBe('out')
        ->and((float) $mov->quantity)->toBe(1.00);
});

test('cash usd payment applies full 16 percent vat and 3 percent igtf add on dynamically', function () {
    $this->actingAs($this->user);

    $shift = CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 0,
        'opening_cash_usd' => 0,
    ]);

    // Product $100 (taxable) + 16% VAT = $116.00.
    // When paid in USD, VAT is full 16% ($16.00) and 3% IGTF applies ($116.00 * 0.03 = $3.48).
    $product = Product::create([
        'name' => 'Canasta Premium',
        'price' => 100.00,
        'stock' => 10,
        'cost' => 70.00,
        'has_vat' => true,
    ]);

    Livewire::test(PosTerminal::class)
        ->call('addToCart', $product->id)
        ->call('openPaymentModal')
        // Add Cash USD payment: $120 USD (bill)
        ->set('selectedPaymentMethod', 'cash_usd')
        ->set('paymentAmount', 120.00)
        ->call('addPayment')
        // Live calculations: VAT is full 16.00, IGTF is 3.48, Total is 119.48
        ->assertSet('fiscalSummary.vat_amount', 16.00)
        ->assertSet('fiscalSummary.igtf_amount', 3.48)
        ->assertSet('fiscalSummary.total_amount_usd', 119.48)
        // Vuelto: 120 - 119.48 = 0.52 USD = 26.00 Bs (at rate 50)
        ->assertSet('fiscalSummary.change_due_usd', 0.52)
        ->assertSet('fiscalSummary.change_due_bs', 26.00)
        ->call('processSale')
        ->assertSet('showTicketModal', true);

    $sale = Sale::latest('id')->first();
    expect((float) $sale->total_vat)->toBe(16.00)
        ->and((float) $sale->total_igtf)->toBe(3.48)
        ->and((float) $sale->total_amount)->toBe(119.48);
});

test('cashier can close shift with blind arqueo and calculate differences', function () {
    $this->actingAs($this->user);

    $shift = CashShift::create([
        'cash_register_id' => $this->register->id,
        'user_id' => $this->user->id,
        'opened_at' => now(),
        'status' => 'open',
        'opening_cash_bs' => 500.00,
        'opening_cash_usd' => 50.00,
        'system_cash_bs' => 500.00,
        'system_cash_usd' => 50.00,
    ]);

    // Perform blind audit closure:
    // Declare 520 Bs (+20 sobrante), 45 USD (-5 faltante)
    Livewire::test(PosTerminal::class)
        ->call('openCloseShiftModal')
        ->assertSet('showShiftCloseModal', true)
        ->set('declaredCashBs', 520.00)
        ->set('declaredCashUsd', 45.00)
        ->set('declaredPosBs', 0.00)
        ->set('declaredMobilePayBs', 0.00)
        ->set('shiftNotes', 'Arqueo de prueba')
        ->call('closeShift')
        ->assertSet('showShiftCloseModal', false)
        ->assertSet('showShiftReportModal', true)
        ->assertSet('activeShiftId', null);

    $closedShift = CashShift::find($shift->id);
    expect($closedShift->status)->toBe('closed')
        ->and((float) $closedShift->difference_cash_bs)->toBe(20.00)
        ->and((float) $closedShift->difference_cash_usd)->toBe(-5.00);
});
