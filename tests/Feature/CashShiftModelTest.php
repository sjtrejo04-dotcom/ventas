<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\CashRegisterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashShiftModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_register_and_shift_relationships(): void
    {
        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja 01 Mostrador',
            'code' => 'CAJA-01',
            'is_active' => true,
        ]);

        $shift = CashShift::create([
            'cash_register_id' => $register->id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'status' => 'open',
            'opening_cash_bs' => 500.00,
            'opening_cash_usd' => 50.00,
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'cash_shift_id' => $shift->id,
            'invoice_number' => 'FACT-001',
            'pos_document_number' => '9-0001',
            'fiscal_serial' => 'TIB2000431',
            'total_base' => 100.00,
            'total_vat' => 16.00,
            'total_igtf' => 0.00,
            'total_amount' => 116.00,
        ]);

        // Test relations
        $this->assertEquals($register->id, $shift->cashRegister->id);
        $this->assertEquals($user->id, $shift->user->id);
        $this->assertTrue($shift->sales->contains($sale));
        $this->assertEquals($shift->id, $sale->cashShift->id);
        $this->assertTrue($register->cashShifts->contains($shift));
        $this->assertTrue($user->cashShifts->contains($shift));
    }

    public function test_sale_item_supports_decimal_quantity_serial_and_warranty(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Queso Llanero por Peso',
            'price' => 10.00,
            'cost' => 5.00,
            'stock' => 50,
            'has_vat' => false,
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'invoice_number' => 'FACT-002',
            'total_base' => 0,
            'total_vat' => 0,
            'total_igtf' => 0,
            'total_amount' => 5.75,
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 0.575, // 575 grams
            'unit_price' => 10.00,
            'unit_cost' => 5.00,
            'vat_amount' => 0,
            'subtotal' => 5.75,
            'serial_number' => 'SN-12345-ABC',
            'warranty_days' => 90,
        ]);

        $this->assertEquals('0.575', (string) $item->fresh()->quantity);
        $this->assertEquals('SN-12345-ABC', $item->fresh()->serial_number);
        $this->assertEquals(90, $item->fresh()->warranty_days);
    }

    public function test_cash_register_seeder_creates_default_register(): void
    {
        $this->seed(CashRegisterSeeder::class);

        $this->assertDatabaseHas('cash_registers', [
            'code' => 'CAJA-01',
            'name' => 'Caja 01 Mostrador',
            'is_active' => true,
        ]);
    }
}
