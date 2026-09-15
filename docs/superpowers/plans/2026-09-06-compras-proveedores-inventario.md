# Compras a Proveedores, Formación de Precios y Control de Inventario Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the complete purchase-to-inventory pipeline, allowing users to register supplier invoices with line items, automatically set product cost and selling price via commercial margin ($\text{unit\_cost} / (1 - \text{margin})$), automatically increase inventory stock via Kardex (`inventory_movements`), and support both cash and credit terms.

**Architecture:** Extend the existing `expenses` and `providers` tables with an `expense_items` line-item table mirroring `sale_items`. Use an `ExpenseItemObserver` to automatically update `Product` cost/price and record an `InventoryMovement` with `type: 'in'`, which triggers the existing `InventoryMovementObserver` to increment `Product.stock`. Enforce binary (G 16% / E 0%) tax brackets and provide a reactive Filament form with cash/credit toggle and on-the-fly product creation.

**Tech Stack:** Laravel 13, FilamentPHP v4, Livewire, PostgreSQL 17 (Supabase), Pest PHP.

**Spec:** `docs/superpowers/specs/2026-09-06-compras-proveedores-inventario-design.md`

## Global Constraints
- Tax alícuotas strictly restricted to **(G)** 16% General and **(E)** 0% Exempt.
- Margin formula strictly uses commercial margin on sales: $\text{selling\_price} = \text{round}(\text{unit\_cost} / (1 - \text{margin} / 100), 2)$.
- Inventory movements must have `type: 'in'`, `unit_cost: unit_cost`, and polymorphic reference `reference_type: Expense::class`, `reference_id: expense_id`.
- Test suite must maintain 100% pass rate (`php artisan test`).
- Code style must pass Laravel Pint (`vendor/bin/pint`).

---

### Task 1: Database Migration & Eloquent Models

**Files:**
- Create: `database/migrations/2026_09_06_210000_add_payment_fields_to_expenses_and_create_expense_items_table.php`
- Create: `app/Models/ExpenseItem.php`
- Modify: `app/Models/Expense.php:12-44`
- Modify: `app/Models/Product.php:20-38`
- Test: `tests/Unit/ExpenseItemModelTest.php`

**Interfaces:**
- Produces: `ExpenseItem` model with relations `expense()`, `product()`.
- Produces: `Expense` relations `items(): HasMany`, `products(): BelongsToMany`, `paymentMethod(): BelongsTo`.
- Produces: `Product` relations `expenseItems(): HasMany`, `expenses(): BelongsToMany`.

- [ ] **Step 1: Write the failing test for models and relationships**

```php
// tests/Unit/ExpenseItemModelTest.php
<?php

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ExpenseItemModelTest`
Expected: FAIL because migration and `ExpenseItem` class do not exist yet.

- [ ] **Step 3: Create migration**

```php
// database/migrations/2026_09_06_210000_add_payment_fields_to_expenses_and_create_expense_items_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('paid')->after('provider_id');
            $table->foreignId('payment_method_id')->nullable()->after('payment_status')->constrained('payment_methods')->nullOnDelete();
            $table->decimal('total_exempt', 15, 2)->default(0.00)->after('total_base');
        });

        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 2)->default(1.00);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('margin_percent', 5, 2)->default(30.00);
            $table->decimal('selling_price', 15, 2);
            $table->boolean('has_vat')->default(true);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('vat_amount', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index('expense_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['payment_method_id']);
            $table->dropColumn(['payment_status', 'payment_method_id', 'total_exempt']);
        });
    }
};
```

Run: `php artisan migrate`

- [ ] **Step 4: Create model `ExpenseItem` and update `Expense` and `Product`**

```php
// app/Models/ExpenseItem.php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_id',
        'product_id',
        'quantity',
        'unit_cost',
        'margin_percent',
        'selling_price',
        'has_vat',
        'subtotal',
        'vat_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'margin_percent' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'has_vat' => 'boolean',
        'subtotal' => 'decimal:2',
        'vat_amount' => 'decimal:2',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

Update `app/Models/Expense.php` fillable and relations:
```php
// Add to $fillable: 'payment_status', 'payment_method_id', 'total_exempt'
// Add to $casts: 'total_exempt' => 'decimal:2'
public function items(): HasMany
{
    return $this->hasMany(ExpenseItem::class);
}

public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'expense_items')
        ->withPivot(['quantity', 'unit_cost', 'margin_percent', 'selling_price', 'has_vat', 'subtotal', 'vat_amount'])
        ->withTimestamps();
}

public function paymentMethod(): BelongsTo
{
    return $this->belongsTo(PaymentMethod::class);
}
```

Update `app/Models/Product.php` relations:
```php
public function expenseItems(): HasMany
{
    return $this->hasMany(ExpenseItem::class);
}

public function expenses(): BelongsToMany
{
    return $this->belongsToMany(Expense::class, 'expense_items')
        ->withPivot(['quantity', 'unit_cost', 'margin_percent', 'selling_price', 'has_vat', 'subtotal', 'vat_amount'])
        ->withTimestamps();
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ExpenseItemModelTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/ app/Models/ExpenseItem.php app/Models/Expense.php app/Models/Product.php tests/Unit/ExpenseItemModelTest.php
git commit -m "feat(purchases): add expense_items table, payment fields to expenses, and model relations"
```

---

### Task 2: Commercial Margin and Purchase Calculation Service

**Files:**
- Create: `app/Services/CommercialCalculationService.php`
- Test: `tests/Unit/CommercialCalculationServiceTest.php`

**Interfaces:**
- Produces: `CommercialCalculationService::calculateSellingPrice(float $unitCost, float $marginPercent): float`
- Produces: `CommercialCalculationService::calculateItemSubtotal(float $quantity, float $unitCost): float`
- Produces: `CommercialCalculationService::calculateItemVat(float $subtotal, bool $hasVat): float`
- Produces: `CommercialCalculationService::calculateInvoiceTotals(array $items): array`

- [ ] **Step 1: Write the failing unit tests for commercial calculations**

```php
// tests/Unit/CommercialCalculationServiceTest.php
<?php

use App\Services\CommercialCalculationService;

test('calculates selling price using commercial margin on sales correctly', function () {
    // $10 cost with 30% margin = $10 / (1 - 0.30) = $14.2857 -> $14.29
    $price = CommercialCalculationService::calculateSellingPrice(10.00, 30.00);
    expect($price)->toBe(14.29);

    // $50 cost with 20% margin = $50 / 0.80 = $62.50
    $price2 = CommercialCalculationService::calculateSellingPrice(50.00, 20.00);
    expect($price2)->toBe(62.50);

    // Margin >= 100 or <= 0 edge cases
    expect(CommercialCalculationService::calculateSellingPrice(10.00, 0.00))->toBe(10.00)
        ->and(CommercialCalculationService::calculateSellingPrice(10.00, 100.00))->toBe(10.00);
});

test('calculates item subtotal and vat correctly for G and E items', function () {
    $subtotal = CommercialCalculationService::calculateItemSubtotal(3, 15.50);
    expect($subtotal)->toBe(46.50);

    // Gravado (G) 16%
    $vatG = CommercialCalculationService::calculateItemVat($subtotal, hasVat: true);
    expect($vatG)->toBe(7.44);

    // Exento (E) 0%
    $vatE = CommercialCalculationService::calculateItemVat($subtotal, hasVat: false);
    expect($vatE)->toBe(0.00);
});

test('calculates invoice totals with mixed G and E items accurately', function () {
    $items = [
        ['quantity' => 2, 'unit_cost' => 50.00, 'has_vat' => true], // Subtotal $100.00, IVA $16.00
        ['quantity' => 1, 'unit_cost' => 30.00, 'has_vat' => false], // Subtotal $30.00, IVA $0.00
    ];

    $totals = CommercialCalculationService::calculateInvoiceTotals($items);

    expect($totals['total_base'])->toBe(100.00)
        ->and($totals['total_exempt'])->toBe(30.00)
        ->and($totals['total_vat'])->toBe(16.00)
        ->and($totals['total_amount'])->toBe(146.00);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CommercialCalculationServiceTest`
Expected: FAIL because `CommercialCalculationService` class does not exist.

- [ ] **Step 3: Implement `CommercialCalculationService`**

```php
// app/Services/CommercialCalculationService.php
<?php

declare(strict_types=1);

namespace App\Services;

class CommercialCalculationService
{
    public const VAT_RATE = 0.16;

    /**
     * Calculate retail selling price using commercial margin on sales: Cost / (1 - Margin%)
     */
    public static function calculateSellingPrice(float $unitCost, float $marginPercent): float
    {
        if ($unitCost <= 0) {
            return 0.0;
        }

        if ($marginPercent <= 0 || $marginPercent >= 100) {
            return round($unitCost, 2);
        }

        $marginDecimal = $marginPercent / 100.0;
        $price = $unitCost / (1.0 - $marginDecimal);

        return round($price, 2);
    }

    public static function calculateItemSubtotal(float $quantity, float $unitCost): float
    {
        return round(max(0.0, $quantity) * max(0.0, $unitCost), 2);
    }

    public static function calculateItemVat(float $subtotal, bool $hasVat): float
    {
        if (! $hasVat || $subtotal <= 0) {
            return 0.0;
        }

        return round($subtotal * self::VAT_RATE, 2);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{total_base: float, total_exempt: float, total_vat: float, total_amount: float}
     */
    public static function calculateInvoiceTotals(array $items): array
    {
        $totalBase = 0.0;
        $totalExempt = 0.0;
        $totalVat = 0.0;

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $cost = (float) ($item['unit_cost'] ?? 0);
            $hasVat = (bool) ($item['has_vat'] ?? true);

            $subtotal = self::calculateItemSubtotal($qty, $cost);
            $vat = self::calculateItemVat($subtotal, $hasVat);

            if ($hasVat) {
                $totalBase += $subtotal;
                $totalVat += $vat;
            } else {
                $totalExempt += $subtotal;
            }
        }

        $totalBase = round($totalBase, 2);
        $totalExempt = round($totalExempt, 2);
        $totalVat = round($totalVat, 2);
        $totalAmount = round($totalBase + $totalExempt + $totalVat, 2);

        return [
            'total_base' => $totalBase,
            'total_exempt' => $totalExempt,
            'total_vat' => $totalVat,
            'total_amount' => $totalAmount,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CommercialCalculationServiceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CommercialCalculationService.php tests/Unit/CommercialCalculationServiceTest.php
git commit -m "feat(purchases): implement CommercialCalculationService for sales margin and tax calculations"
```

---

### Task 3: Kardex & Catalog Automation via `ExpenseItemObserver`

**Files:**
- Create: `app/Observers/ExpenseItemObserver.php`
- Modify: `app/Providers/AppServiceProvider.php:25-35`
- Test: `tests/Feature/ExpenseInventoryIntegrationTest.php`

**Interfaces:**
- Produces: `ExpenseItemObserver` on `created` event creates `InventoryMovement` (`type: 'in'`) and updates `Product.cost` and `Product.price`.

- [ ] **Step 1: Write integration test for automatic Kardex and product update**

```php
// tests/Feature/ExpenseInventoryIntegrationTest.php
<?php

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ExpenseInventoryIntegrationTest`
Expected: FAIL because `ExpenseItemObserver` does not exist yet.

- [ ] **Step 3: Implement `ExpenseItemObserver`**

```php
// app/Observers/ExpenseItemObserver.php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\InventoryMovement;

class ExpenseItemObserver
{
    public function created(ExpenseItem $item): void
    {
        $product = $item->product;
        if (! $product) {
            return;
        }

        // 1. Update product cost and selling price with replacement values
        $product->update([
            'cost' => $item->unit_cost,
            'price' => $item->selling_price,
        ]);

        // 2. Record Kardex movement (this automatically triggers InventoryMovementObserver to increment product.stock)
        $expense = $item->expense;
        $providerName = $expense?->provider?->name ?? 'Proveedor';
        $invoiceNumber = $expense?->invoice_number ?? '';

        InventoryMovement::create([
            'product_id' => $item->product_id,
            'user_id' => $expense?->user_id ?? auth()->id(),
            'type' => 'in',
            'concept' => trim("Compra Proveedor: {$providerName} - Factura #{$invoiceNumber}"),
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'reference_type' => (new Expense)->getMorphClass(),
            'reference_id' => $item->expense_id,
        ]);
    }
}
```

- [ ] **Step 4: Register `ExpenseItemObserver` in `AppServiceProvider`**

```php
// app/Providers/AppServiceProvider.php
use App\Models\ExpenseItem;
use App\Observers\ExpenseItemObserver;

// Inside boot():
ExpenseItem::observe(ExpenseItemObserver::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=ExpenseInventoryIntegrationTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Observers/ExpenseItemObserver.php app/Providers/AppServiceProvider.php tests/Feature/ExpenseInventoryIntegrationTest.php
git commit -m "feat(purchases): implement ExpenseItemObserver to automate Kardex entry and product price updates"
```

---

### Task 4: Filament Admin UI (`ExpenseResource`, `ExpenseForm`, `ExpensesTable`)

**Files:**
- Modify: `app/Filament/Admin/Resources/Expenses/ExpenseResource.php:20-47`
- Modify: `app/Filament/Admin/Resources/Expenses/Schemas/ExpenseForm.php:1-74`
- Modify: `app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php:1-60`
- Test: `tests/Feature/ExpenseResourceTest.php`

**Interfaces:**
- Configures `ExpenseResource` with navigation label 'Compras a Proveedores', group 'Finanzas & Contabilidad', icon `heroicon-o-truck`.
- Configures `ExpenseForm` with provider info, payment condition (Contado/Crédito), line items repeater with auto-margin calculation and on-the-fly product creation.

- [x] **Step 1: Write feature test for `ExpenseResource` page rendering and creation**

```php
// tests/Feature/ExpenseResourceTest.php
<?php

use App\Filament\Admin\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Admin\Resources\Expenses\Pages\ListExpenses;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Livewire\Livewire;

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
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ExpenseResourceTest`
Expected: FAIL because `ExpenseForm` does not have `items` repeater and payment fields yet.

- [x] **Step 3: Update `ExpenseResource.php`**

```php
// app/Filament/Admin/Resources/Expenses/ExpenseResource.php
protected static bool $shouldRegisterNavigation = true;

public static function getModelLabel(): string
{
    return 'Compra a Proveedor';
}

public static function getPluralModelLabel(): string
{
    return 'Compras a Proveedores';
}

public static function getNavigationLabel(): string
{
    return 'Compras a Proveedores';
}

public static function getNavigationGroup(): string|UnitEnum|null
{
    return 'Finanzas & Contabilidad';
}
```

- [x] **Step 4: Update `ExpenseForm.php` with Repeater and live reactive calculations**

```php
// app/Filament/Admin/Resources/Expenses/Schemas/ExpenseForm.php
<?php

namespace App\Filament\Admin\Resources\Expenses\Schemas;

use App\Models\Product;
use App\Services\CommercialCalculationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
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
                            ->required(),
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
                    ])->columns(3)->columnSpan('full'),

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
                                        TextInput::make('name')->label('Nombre del Producto')->required(),
                                        TextInput::make('description')->label('Descripción'),
                                        Toggle::make('has_vat')->label('¿Aplica IVA 16% (G)?')->default(true),
                                    ])
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($product = Product::find($state)) {
                                            $set('has_vat', (bool) $product->has_vat);
                                            if (! $get('unit_cost') && $product->cost > 0) {
                                                $set('unit_cost', (float) $product->cost);
                                            }
                                        }
                                        self::updateItemCalculations($get, $set);
                                    })
                                    ->columnSpan(3),

                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan(2),

                                TextInput::make('unit_cost')
                                    ->label('Costo Proveedor ($)')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->required()
                                    ->prefix('$')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan(2),

                                TextInput::make('margin_percent')
                                    ->label('Margen (%)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(99.99)
                                    ->default(30.00)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan(2),

                                TextInput::make('selling_price')
                                    ->label('PVP Venta ($)')
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->prefix('$')
                                    ->helperText('PVP = Costo / (1 - Margen%)')
                                    ->columnSpan(2),

                                Toggle::make('has_vat')
                                    ->label('IVA 16% (G)')
                                    ->default(true)
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateItemCalculations($get, $set))
                                    ->columnSpan(1),

                                TextInput::make('subtotal')
                                    ->label('Subtotal ($)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->columnSpan(2),

                                TextInput::make('vat_amount')
                                    ->label('Monto IVA ($)')
                                    ->numeric()
                                    ->prefix('$')
                                    ->readOnly()
                                    ->columnSpan(2),
                            ])
                            ->columns(16)
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateInvoiceTotals($get, $set);
                            }),
                    ]),

                Section::make('Liquidación Fiscal de la Factura')
                    ->schema([
                        TextInput::make('total_base')
                            ->label('Base Imponible 16% (G)')
                            ->prefix('$')
                            ->readOnly()
                            ->default(0),
                        TextInput::make('total_exempt')
                            ->label('Monto Exento (E)')
                            ->prefix('$')
                            ->readOnly()
                            ->default(0),
                        TextInput::make('total_vat')
                            ->label('IVA 16% (Crédito Fiscal)')
                            ->prefix('$')
                            ->readOnly()
                            ->default(0),
                        TextInput::make('total_amount')
                            ->label('Total Factura Proveedor')
                            ->prefix('$')
                            ->readOnly()
                            ->default(0),
                    ])->columns(4)->columnSpanFull(),
            ]);
    }

    public static function updateItemCalculations(Get $get, Set $set): void
    {
        $qty = (float) ($get('quantity') ?? 1);
        $cost = (float) ($get('unit_cost') ?? 0);
        $margin = (float) ($get('margin_percent') ?? 30);
        $hasVat = (bool) ($get('has_vat') ?? true);

        $sellingPrice = CommercialCalculationService::calculateSellingPrice($cost, $margin);
        $subtotal = CommercialCalculationService::calculateItemSubtotal($qty, $cost);
        $vat = CommercialCalculationService::calculateItemVat($subtotal, $hasVat);

        $set('selling_price', $sellingPrice);
        $set('subtotal', $subtotal);
        $set('vat_amount', $vat);
    }

    public static function updateInvoiceTotals(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $totals = CommercialCalculationService::calculateInvoiceTotals($items);

        $set('total_base', $totals['total_base']);
        $set('total_exempt', $totals['total_exempt']);
        $set('total_vat', $totals['total_vat']);
        $set('total_amount', $totals['total_amount']);
    }
}
```

- [x] **Step 5: Update `ExpensesTable.php` with useful purchase badges**

Add columns:
- `invoice_number`
- `provider.name`
- `invoice_date`
- `payment_status` (badge: 'paid' => 'Contado' (success), 'pending' => 'A Crédito' (warning))
- `total_base`
- `total_vat`
- `total_amount`

- [x] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ExpenseResourceTest`
Expected: PASS

- [x] **Step 7: Commit**

```bash
git add app/Filament/Admin/Resources/Expenses/ tests/Feature/ExpenseResourceTest.php
git commit -m "feat(purchases): update ExpenseResource UI with items repeater, auto-margins and payment conditions"
```

---

### Task 5: End-to-End Verification with Playwright and Test Suite

**Files:**
- Test: Full test suite `php artisan test`
- Manual / Playwright verification on `http://127.0.0.1:8000/admin/expenses` and `/admin/pos`

- [x] **Step 1: Run complete automated test suite**

Run: `php artisan test`
Expected: 100% of tests passing (including existing 56 tests + new tests).

- [x] **Step 2: Run code linter**

Run: `vendor/bin/pint`
Expected: 0 errors.

- [x] **Step 3: Playwright live verification**

1. Navigate to `http://127.0.0.1:8000/admin/expenses/create`.
2. Register a purchase invoice for a test product with:
   - Quantity: 5
   - Unit Cost: $20.00
   - Margin: 30% -> calculated selling price: $28.57
   - Save invoice.
3. Check `/admin/products`:
   - Verify stock has increased by 5.
   - Verify cost is updated to $20.00.
   - Verify price is updated to $28.57.
4. Check `/admin/pos`:
   - Verify POS catalog reflects the new stock and price immediately.

- [x] **Step 4: Commit and Push**

```bash
git push origin main
```
