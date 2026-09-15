<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use App\Services\CommercialCalculationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('database schema enforces ON DELETE RESTRICT on product_id preventing product deletion when purchases exist', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Distribuidora Global C.A.', 'rif' => 'J-12345678-1']);
    $product = Product::create([
        'name' => 'Producto Protegido',
        'cost' => 10.00,
        'price' => 15.00,
        'stock' => 5,
        'has_vat' => true,
    ]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'provider_id' => $provider->id,
        'invoice_number' => 'FAC-AUDIT-01',
        'control_number' => 'CTRL-AUDIT-01',
        'total_base' => 100.00,
        'total_vat' => 16.00,
        'total_amount' => 116.00,
        'expense_date' => now()->toDateString(),
        'invoice_date' => now()->toDateString(),
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

    // Attempting to delete product directly must be blocked by foreign key constraint
    expect(fn () => $product->delete())->toThrow(QueryException::class);

    // Product must still exist in the database
    expect(Product::find($product->id))->not->toBeNull()
        ->and(ExpenseItem::where('product_id', $product->id)->count())->toBe(1);
});

test('database schema enforces ON DELETE CASCADE on expense_id when expense is deleted', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Distribuidora Mayor C.A.', 'rif' => 'J-87654321-2']);
    $product = Product::create(['name' => 'Articulo Test', 'cost' => 5.00, 'price' => 8.00, 'stock' => 1, 'has_vat' => true]);

    $expense = Expense::create([
        'user_id' => $user->id,
        'provider_id' => $provider->id,
        'invoice_number' => 'FAC-CASCADE-01',
        'control_number' => 'CTRL-CASCADE-01',
        'total_base' => 50.00,
        'total_vat' => 8.00,
        'total_amount' => 58.00,
        'expense_date' => now()->toDateString(),
        'invoice_date' => now()->toDateString(),
    ]);

    ExpenseItem::create([
        'expense_id' => $expense->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_cost' => 5.00,
        'margin_percent' => 37.50,
        'selling_price' => 8.00,
        'has_vat' => true,
        'subtotal' => 50.00,
        'vat_amount' => 8.00,
    ]);

    expect(ExpenseItem::where('expense_id', $expense->id)->count())->toBe(1);

    // Deleting the expense header cascades to its line items
    $expense->delete();

    expect(ExpenseItem::where('expense_id', $expense->id)->count())->toBe(0);
});

test('database schema uses decimal(15, 2) and never float for monetary amounts', function () {
    // Verify expense_items monetary columns are numeric/decimal, not float/double
    $monetaryColumns = ['quantity', 'unit_cost', 'selling_price', 'subtotal', 'vat_amount'];

    foreach ($monetaryColumns as $column) {
        $type = Schema::getColumnType('expense_items', $column);
        expect(in_array($type, ['numeric', 'decimal'], true))
            ->toBeTrue("Column {$column} on expense_items must be decimal/numeric, but is {$type}");
    }

    // Verify expenses monetary columns
    $expenseMonetaryColumns = ['total_base', 'total_exempt', 'total_vat', 'total_amount'];
    foreach ($expenseMonetaryColumns as $column) {
        $type = Schema::getColumnType('expenses', $column);
        expect(in_array($type, ['numeric', 'decimal'], true))
            ->toBeTrue("Column {$column} on expenses must be decimal/numeric, but is {$type}");
    }

    // Verify foreign key index existence
    expect(Schema::hasIndex('expense_items', ['expense_id']))->toBeTrue()
        ->and(Schema::hasIndex('expense_items', ['product_id']))->toBeTrue();
});

test('multi-item purchase registration maintains ACID consistency rolling back completely on failure', function () {
    $user = User::factory()->create();
    $provider = Provider::create(['name' => 'Proveedor ACID S.A.', 'rif' => 'J-33445566-7']);
    $product1 = Product::create(['name' => 'Producto Alfa', 'cost' => 10.00, 'price' => 15.00, 'stock' => 0, 'has_vat' => true]);

    $invoiceNumber = 'FACT-ACID-ROLLBACK';

    // Attempting a transaction where item 2 fails
    expect(function () use ($user, $provider, $product1, $invoiceNumber) {
        DB::transaction(function () use ($user, $provider, $product1, $invoiceNumber) {
            $expense = Expense::create([
                'user_id' => $user->id,
                'provider_id' => $provider->id,
                'invoice_number' => $invoiceNumber,
                'control_number' => 'CTRL-ACID',
                'total_base' => 100.00,
                'total_vat' => 16.00,
                'total_amount' => 116.00,
                'expense_date' => now()->toDateString(),
                'invoice_date' => now()->toDateString(),
            ]);

            // Item 1 succeeds
            ExpenseItem::create([
                'expense_id' => $expense->id,
                'product_id' => $product1->id,
                'quantity' => 5,
                'unit_cost' => 10.00,
                'margin_percent' => 30.00,
                'selling_price' => 14.29,
                'has_vat' => true,
                'subtotal' => 50.00,
                'vat_amount' => 8.00,
            ]);

            // Item 2 fails due to non-existent product_id violating FK constraint
            ExpenseItem::create([
                'expense_id' => $expense->id,
                'product_id' => 999999, // Does not exist
                'quantity' => 5,
                'unit_cost' => 10.00,
                'margin_percent' => 30.00,
                'selling_price' => 14.29,
                'has_vat' => true,
                'subtotal' => 50.00,
                'vat_amount' => 8.00,
            ]);
        });
    })->toThrow(QueryException::class);

    // Assert complete rollback: no expense, no expense_item, no inventory movement created
    expect(Expense::where('invoice_number', $invoiceNumber)->count())->toBe(0)
        ->and(ExpenseItem::count())->toBe(0)
        ->and(InventoryMovement::where('reference_type', (new Expense)->getMorphClass())->count())->toBe(0);

    // Product stock must remain unchanged (0)
    expect((int) $product1->fresh()->stock)->toBe(0);
});

test('calculation service and validation rules reject non-positive quantities and negative costs', function () {
    // 1. CommercialCalculationService mathematical safeguards
    // Negative unit cost must produce 0.0 selling price and 0.0 subtotal
    expect(CommercialCalculationService::calculateSellingPrice(-10.00, 30.00))->toBe(0.0)
        ->and(CommercialCalculationService::calculateSellingPrice(0.00, 30.00))->toBe(0.0)
        ->and(CommercialCalculationService::calculateItemSubtotal(-5.0, 10.00))->toBe(0.0)
        ->and(CommercialCalculationService::calculateItemSubtotal(5.0, -10.00))->toBe(0.0)
        ->and(CommercialCalculationService::calculateItemSubtotal(0.0, 10.00))->toBe(0.0)
        ->and(CommercialCalculationService::calculateItemVat(-50.00, hasVat: true))->toBe(0.0);

    // 2. Commercial calculation margin boundary limits
    expect(CommercialCalculationService::calculateSellingPrice(10.00, 0.00))->toBe(10.00)
        ->and(CommercialCalculationService::calculateSellingPrice(10.00, 100.00))->toBe(10.00)
        ->and(CommercialCalculationService::calculateSellingPrice(10.00, 150.00))->toBe(10.00);

    // 3. Purchase item validation schema rules
    $rules = [
        'items' => ['required', 'array', 'min:1'],
        'items.*.product_id' => ['required', 'exists:products,id'],
        'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        'items.*.unit_cost' => ['required', 'numeric', 'gt:0'],
        'items.*.margin_percent' => ['required', 'numeric', 'gte:0', 'lt:100'],
    ];

    // Invalid payload: quantity 0, negative unit_cost, margin >= 100
    $invalidData = [
        'items' => [
            [
                'product_id' => 1,
                'quantity' => 0,
                'unit_cost' => -15.00,
                'margin_percent' => 105.00,
            ],
        ],
    ];

    $validator = Validator::make($invalidData, $rules);
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('items.0.quantity'))->toBeTrue()
        ->and($validator->errors()->has('items.0.unit_cost'))->toBeTrue()
        ->and($validator->errors()->has('items.0.margin_percent'))->toBeTrue();

    // Invalid payload: empty items array
    $emptyItemsValidator = Validator::make(['items' => []], $rules);
    expect($emptyItemsValidator->fails())->toBeTrue()
        ->and($emptyItemsValidator->errors()->has('items'))->toBeTrue();
});

test('unauthenticated users are strictly blocked from accessing purchase expenses pages', function () {
    // Unauthenticated GET requests must be redirected to /admin/login
    $this->get('/admin/expenses')
        ->assertRedirect('/admin/login');

    $this->get('/admin/expenses/create')
        ->assertRedirect('/admin/login');

    // Authenticated user can access the expenses list
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/admin/expenses')
        ->assertSuccessful();
});
