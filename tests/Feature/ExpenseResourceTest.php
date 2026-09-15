<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('can render list expenses page and create an expense with line items', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Distribuciones El Ávila C.A.', 'rif' => 'J-11223344-5']);
    $paymentMethod = PaymentMethod::create(['name' => 'Efectivo Divisas', 'requires_reference' => false, 'applies_igtf' => true, 'is_active' => true]);
    $product = Product::create(['name' => 'Audífonos Bluetooth', 'price' => 20.00, 'cost' => 10.00, 'has_vat' => true, 'stock' => 0]);

    $this->actingAs($user);

    Livewire::test(ListExpenses::class)
        ->assertSuccessful();

    Livewire::test(CreateExpense::class)
        ->fillForm([
            'provider_id' => $provider->id,
            'payment_status' => 'paid',
            'payment_method_id' => $paymentMethod->id,
            'invoice_number' => 'FAC-7788',
            'control_number' => 'CTRL-7788',
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 15,
                    'unit_cost' => 12.00,
                    'margin_percent' => 30.00,
                    'selling_price' => 17.14,
                    'has_vat' => true,
                    'subtotal' => 180.00,
                    'vat_amount' => 28.80,
                ],
            ],
            'total_base' => 180.00,
            'total_exempt' => 0.00,
            'total_vat' => 28.80,
            'total_amount' => 208.80,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product->refresh();
    expect((int) $product->stock)->toBe(15)
        ->and((float) $product->cost)->toBe(12.00)
        ->and((float) $product->price)->toBe(17.14);

    $expense = Expense::where('invoice_number', 'FAC-7788')->first();
    expect($expense)->not->toBeNull()
        ->and($expense->payment_status)->toBe('paid')
        ->and($expense->provider_id)->toBe($provider->id)
        ->and((float) $expense->total_amount)->toBe(208.80)
        ->and($expense->items)->toHaveCount(1);
});

test('can create a credit expense with due date and exempt items', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Comercializadora Médica S.A.', 'rif' => 'J-44332211-9']);
    $product = Product::create(['name' => 'Mascarillas Quirúrgicas', 'price' => 5.00, 'cost' => 2.00, 'has_vat' => false, 'stock' => 10]);

    $this->actingAs($user);

    Livewire::test(CreateExpense::class)
        ->fillForm([
            'provider_id' => $provider->id,
            'payment_status' => 'pending',
            'due_date' => now()->addDays(30)->toDateString(),
            'invoice_number' => 'FAC-CRED-101',
            'control_number' => 'CTRL-CRED-101',
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 50,
                    'unit_cost' => 2.50,
                    'margin_percent' => 20.00,
                    'selling_price' => 3.13,
                    'has_vat' => false,
                    'subtotal' => 125.00,
                    'vat_amount' => 0.00,
                ],
            ],
            'total_base' => 0.00,
            'total_exempt' => 125.00,
            'total_vat' => 0.00,
            'total_amount' => 125.00,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product->refresh();
    expect((int) $product->stock)->toBe(60)
        ->and((float) $product->cost)->toBe(2.50)
        ->and((float) $product->price)->toBe(3.13);

    $expense = Expense::where('invoice_number', 'FAC-CRED-101')->first();
    expect($expense)->not->toBeNull()
        ->and($expense->payment_status)->toBe('pending')
        ->and($expense->payment_method_id)->toBeNull()
        ->and((float) $expense->total_exempt)->toBe(125.00)
        ->and((float) $expense->total_vat)->toBe(0.00);
});
