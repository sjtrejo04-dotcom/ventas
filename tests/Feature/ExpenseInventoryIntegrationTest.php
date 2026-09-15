<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('creating expense item updates product cost and price and increments stock via inventory movement', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Distribuidora Central S.A.', 'rif' => 'J-99887766-5']);

    $product = Product::create([
        'name' => 'Zapato Deportivo Alpha',
        'cost' => 8.00,
        'price' => 12.00,
        'stock' => 10,
        'has_vat' => true,
    ]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'provider_id' => $provider->id,
        'payment_status' => 'paid',
        'invoice_number' => 'FACT-1001',
        'control_number' => '00-1001',
        'total_base' => 120.00,
        'total_exempt' => 0.00,
        'total_vat' => 19.20,
        'total_amount' => 139.20,
        'expense_date' => now()->toDateString(),
        'invoice_date' => now()->toDateString(),
    ]);

    // Receive 10 units at new replacement cost $12.00 with 30% margin ($17.14)
    $item = ExpenseItem::create([
        'expense_id' => $expense->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 12.00,
        'margin_percent' => 30.00,
        'selling_price' => 17.14,
        'has_vat' => true,
        'subtotal' => 120.00,
        'vat_amount' => 19.20,
    ]);

    $product->refresh();

    // 1. Stock must be incremented by 10 (10 existing + 10 received = 20)
    expect((int) $product->stock)->toBe(20)
        // 2. Cost must be updated to new replacement cost $12.00
        ->and((float) $product->cost)->toBe(12.00)
        // 3. Price must be updated to new selling price $17.14
        ->and((float) $product->price)->toBe(17.14);

    // 4. Inventory movement must be recorded with reference to Expense
    $movement = InventoryMovement::where('product_id', $product->id)
        ->where('type', 'in')
        ->latest('id')
        ->first();

    expect($movement)->not->toBeNull()
        ->and((float) $movement->quantity)->toBe(10.00)
        ->and((float) $movement->unit_cost)->toBe(12.00)
        ->and((float) $movement->stock_after_movement)->toBe(20.00)
        ->and($movement->reference_type)->toBe((new Expense)->getMorphClass())
        ->and((int) $movement->reference_id)->toBe($expense->id);
});
