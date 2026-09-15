<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('expense item belongs to expense and product and computes subtotal correctly', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Distribuidora Mayorista C.A.', 'rif' => 'J-12345678-9']);
    $paymentMethod = PaymentMethod::create(['name' => 'Transferencia Bs', 'requires_reference' => true, 'applies_igtf' => false, 'is_active' => true]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'provider_id' => $provider->id,
        'payment_method_id' => $paymentMethod->id,
        'payment_status' => 'paid',
        'invoice_number' => 'FACT-9988',
        'control_number' => '00-9988',
        'total_base' => 100.00,
        'total_exempt' => 0.00,
        'total_vat' => 16.00,
        'total_amount' => 116.00,
        'expense_date' => now()->toDateString(),
        'invoice_date' => now()->toDateString(),
    ]);

    $product = Product::create([
        'name' => 'Zapatos Casuales de Prueba',
        'price' => 14.29,
        'cost' => 10.00,
        'has_vat' => true,
        'stock' => 5,
    ]);

    $item = ExpenseItem::create([
        'expense_id' => $expense->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 10.00,
        'margin_percent' => 30.00,
        'selling_price' => 14.29,
        'has_vat' => true,
        'subtotal' => 100.00,
        'vat_amount' => 16.00,
    ]);

    expect($item->expense->id)->toBe($expense->id)
        ->and($item->product->id)->toBe($product->id)
        ->and($item->unit_cost)->toBe('10.00')
        ->and($expense->items)->toHaveCount(1)
        ->and($expense->products->first()->id)->toBe($product->id)
        ->and($product->expenseItems)->toHaveCount(1);
});
