<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_item_creation_creates_inventory_movement_and_updates_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Harina PAN 1kg',
            'price' => 100.00,
            'cost' => 50.00,
            'stock' => 10,
            'has_vat' => false,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'invoice_number' => 'FACT-001',
        ]);

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
            'reference_type' => $sale->getMorphClass(),
            'reference_id' => $sale->id,
        ]);

        // Check if stock was updated via the movement observer
        $this->assertEquals(9, $product->fresh()->stock);
    }

    public function test_sale_item_update_increasing_quantity_creates_out_movement_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Arroz Mary 1kg',
            'price' => 50.00,
            'cost' => 25.00,
            'stock' => 20,
            'has_vat' => false,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'invoice_number' => 'FACT-002',
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
            'unit_cost' => 25,
            'vat_amount' => 0,
            'subtotal' => 100,
        ]);

        // Update quantity from 2 to 5 (+3 items sold)
        $saleItem->update(['quantity' => 5]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 3,
            'reference_type' => $sale->getMorphClass(),
            'reference_id' => $sale->id,
        ]);

        // Initial 20 - 2 (on create) - 3 (on update) = 15
        $this->assertEquals(15, $product->fresh()->stock);
    }

    public function test_sale_item_update_decreasing_quantity_creates_in_movement_and_increments_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Aceite Mazeite 1L',
            'price' => 80.00,
            'cost' => 40.00,
            'stock' => 20,
            'has_vat' => false,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'invoice_number' => 'FACT-003',
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 80,
            'unit_cost' => 40,
            'vat_amount' => 0,
            'subtotal' => 400,
        ]);

        // Update quantity from 5 to 2 (-3 items returned)
        $saleItem->update(['quantity' => 2]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 3,
            'reference_type' => $sale->getMorphClass(),
            'reference_id' => $sale->id,
        ]);

        // Initial 20 - 5 (on create) + 3 (on update) = 18
        $this->assertEquals(18, $product->fresh()->stock);
    }

    public function test_sale_item_deletion_creates_in_movement_and_restores_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Cafe Fama 500g',
            'price' => 60.00,
            'cost' => 30.00,
            'stock' => 10,
            'has_vat' => false,
        ]);
        $sale = Sale::create([
            'user_id' => $user->id,
            'invoice_number' => 'FACT-004',
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 60,
            'unit_cost' => 30,
            'vat_amount' => 0,
            'subtotal' => 180,
        ]);

        $saleItem->delete();

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 3,
            'reference_type' => $sale->getMorphClass(),
            'reference_id' => $sale->id,
        ]);

        // Initial 10 - 3 (create) + 3 (delete) = 10
        $this->assertEquals(10, $product->fresh()->stock);
    }
}
