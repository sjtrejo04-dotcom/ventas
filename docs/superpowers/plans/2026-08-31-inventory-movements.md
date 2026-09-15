# Inventory Movement Logic Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the logic where sales automatically create an audit log (InventoryMovement), which in turn modifies the product stock.

**Architecture:** We will refactor `SaleItemObserver` so it stops modifying `Product` stock directly. Instead, it will create `InventoryMovement` records. The existing `InventoryMovementObserver` will then handle calculating the `stock_after_movement` and updating the `Product` stock automatically. This implements a strict audit-first flow.

**Tech Stack:** Laravel, PHP, Eloquent Observers

**Spec:** User prompt requirement.

## Global Constraints

- Code must use strict types (`declare(strict_types=1);`).
- Do not remove existing logic from `InventoryMovementObserver`.
- `InventoryMovement` creation handles stock updates natively.

---

### Task 1: Refactor SaleItemObserver to use InventoryMovement

**Files:**
- Modify: `app/Observers/SaleItemObserver.php`

**Interfaces:**
- Consumes: `App\Models\InventoryMovement` and `App\Models\SaleItem`

- [ ] **Step 1: Write the failing test**

```php
// tests/Feature/InventoryMovementLogicTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryMovementLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_item_creation_creates_inventory_movement_and_updates_stock()
    {
        $product = Product::factory()->create(['stock' => 10, 'cost' => 50]);
        $sale = Sale::factory()->create();

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'unit_cost' => 50,
            'vat_amount' => 0,
            'subtotal' => 100,
        ]);

        // Check if movement exists
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 1,
        ]);

        // Check if stock was updated via the movement observer
        $this->assertEquals(9, $product->fresh()->stock);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter InventoryMovementLogicTest`
Expected: The test will fail because `SaleItemObserver` doesn't create `InventoryMovement`s yet (it only directly decrements).

- [ ] **Step 3: Write minimal implementation**

Update `app/Observers/SaleItemObserver.php` to completely replace direct stock manipulation with `InventoryMovement` creation.

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use App\Models\SaleItem;
use App\Models\InventoryMovement;

class SaleItemObserver
{
    public function created(SaleItem $saleItem): void
    {
        if ($saleItem->product) {
            InventoryMovement::create([
                'product_id' => $saleItem->product_id,
                'type' => 'out',
                'quantity' => $saleItem->quantity,
                'unit_cost' => $saleItem->unit_cost ?? 0,
                'concept' => 'Venta #' . $saleItem->sale_id,
                'reference_type' => $saleItem->sale->getMorphClass(),
                'reference_id' => $saleItem->sale_id,
            ]);
        }
    }

    public function updated(SaleItem $saleItem): void
    {
        if ($saleItem->isDirty('quantity')) {
            $difference = (float) $saleItem->quantity - (float) $saleItem->getOriginal('quantity');
            
            if ($difference > 0) {
                // More items sold, stock goes out
                InventoryMovement::create([
                    'product_id' => $saleItem->product_id,
                    'type' => 'out',
                    'quantity' => $difference,
                    'unit_cost' => $saleItem->unit_cost ?? 0,
                    'concept' => 'Ajuste de Venta (+ cantidad) #' . $saleItem->sale_id,
                    'reference_type' => $saleItem->sale->getMorphClass(),
                    'reference_id' => $saleItem->sale_id,
                ]);
            } elseif ($difference < 0) {
                // Fewer items sold, stock comes back in
                InventoryMovement::create([
                    'product_id' => $saleItem->product_id,
                    'type' => 'in',
                    'quantity' => abs($difference),
                    'unit_cost' => $saleItem->unit_cost ?? 0,
                    'concept' => 'Ajuste de Venta (- cantidad) #' . $saleItem->sale_id,
                    'reference_type' => $saleItem->sale->getMorphClass(),
                    'reference_id' => $saleItem->sale_id,
                ]);
            }
        }
    }

    public function deleted(SaleItem $saleItem): void
    {
        if ($saleItem->product) {
            InventoryMovement::create([
                'product_id' => $saleItem->product_id,
                'type' => 'in',
                'quantity' => $saleItem->quantity,
                'unit_cost' => $saleItem->unit_cost ?? 0,
                'concept' => 'Cancelación/Eliminación de Venta #' . $saleItem->sale_id,
                'reference_type' => $saleItem->sale->getMorphClass(),
                'reference_id' => $saleItem->sale_id,
            ]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter InventoryMovementLogicTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Observers/SaleItemObserver.php tests/Feature/InventoryMovementLogicTest.php
git commit -m "feat: refactor SaleItemObserver to use InventoryMovement for stock audit"
```

---

### Task 2: Validate existing InventoryMovementObserver and Model

**Files:**
- Modify: None. Just review and confirm, maybe add a test if we need to.

**Interfaces:**
- Consumes: `InventoryMovement`

- [ ] **Step 1: Write a test for deletion/adjustment scenarios**

```php
// tests/Feature/InventoryMovementLogicTest.php (append to the class)
    public function test_sale_item_deletion_restores_stock_via_movement()
    {
        $product = Product::factory()->create(['stock' => 9, 'cost' => 50]);
        $sale = Sale::factory()->create();

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'unit_cost' => 50,
            'vat_amount' => 0,
            'subtotal' => 100,
        ]);

        $saleItem->delete();

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 1,
            'concept' => 'Cancelación/Eliminación de Venta #' . $sale->id,
        ]);

        $this->assertEquals(9, $product->fresh()->stock); // Assuming initial 9, minus 1 then plus 1 = 9.
    }
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --filter InventoryMovementLogicTest`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/InventoryMovementLogicTest.php
git commit -m "test: verify sale item deletion creates returning inventory movement"
```
