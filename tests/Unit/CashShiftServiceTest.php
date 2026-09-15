<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Services\CashShiftService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashShiftServiceTest extends TestCase
{
    use RefreshDatabase;

    private CashShiftService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CashShiftService;
    }

    public function test_can_open_shift_successfully(): void
    {
        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja 01 Mostrador',
            'code' => 'CAJA-01',
            'is_active' => true,
        ]);

        $shift = $this->service->openShift(
            cashRegisterId: $register->id,
            userId: $user->id,
            openingBs: 1000.00,
            openingUsd: 100.00
        );

        $this->assertInstanceOf(CashShift::class, $shift);
        $this->assertEquals('open', $shift->status);
        $this->assertEquals(1000.00, $shift->opening_cash_bs);
        $this->assertEquals(100.00, $shift->opening_cash_usd);
        $this->assertTrue($shift->isOpen());
        $this->assertFalse($shift->isClosed());
    }

    public function test_cannot_open_two_shifts_on_same_cash_register(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja 01 Mostrador',
            'code' => 'CAJA-01',
            'is_active' => true,
        ]);

        $this->service->openShift($register->id, $user1->id, 500, 50);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La caja CAJA-01 ya tiene un turno abierto');

        $this->service->openShift($register->id, $user2->id, 500, 50);
    }

    public function test_cannot_open_two_shifts_for_same_user(): void
    {
        $user = User::factory()->create();
        $register1 = CashRegister::create([
            'name' => 'Caja 01 Mostrador',
            'code' => 'CAJA-01',
            'is_active' => true,
        ]);
        $register2 = CashRegister::create([
            'name' => 'Caja 02 Pasillo',
            'code' => 'CAJA-02',
            'is_active' => true,
        ]);

        $this->service->openShift($register1->id, $user->id, 500, 50);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El usuario ya tiene un turno abierto activo');

        $this->service->openShift($register2->id, $user->id, 500, 50);
    }

    public function test_close_shift_calculates_blind_arqueo_differences(): void
    {
        $user = User::factory()->create();
        $register = CashRegister::create([
            'name' => 'Caja 01 Mostrador',
            'code' => 'CAJA-01',
            'is_active' => true,
        ]);

        $shift = $this->service->openShift($register->id, $user->id, 500.00, 50.00);

        // Record a sale with payments in this shift
        $sale = Sale::create([
            'user_id' => $user->id,
            'cash_shift_id' => $shift->id,
            'invoice_number' => 'FACT-001',
            'total_amount' => 150.00,
        ]);

        $pmCashBs = PaymentMethod::create([
            'name' => 'Efectivo (Bs)',
            'applies_igtf' => false,
        ]);
        $pmCashUsd = PaymentMethod::create([
            'name' => 'Efectivo Divisas ($)',
            'applies_igtf' => true,
        ]);
        $pmPos = PaymentMethod::create([
            'name' => 'Punto de Venta',
            'applies_igtf' => false,
        ]);

        Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $pmCashBs->id,
            'amount' => 200.00,
        ]);
        Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $pmCashUsd->id,
            'amount' => 30.00,
        ]);
        Payment::create([
            'sale_id' => $sale->id,
            'payment_method_id' => $pmPos->id,
            'amount' => 1500.00,
        ]);

        // Expected System Totals:
        // Cash Bs: 500 opening + 200 sale = 700.00
        // Cash USD: 50 opening + 30 sale = 80.00
        // POS Bs: 1500.00

        // Cajero blind declaration:
        // Declared Cash Bs: 720.00 (Sobrante +20)
        // Declared Cash USD: 75.00 (Faltante -5)
        // Declared POS: 1500.00 (Exacto 0)
        $closedShift = $this->service->closeShift($shift, [
            'declared_cash_bs' => 720.00,
            'declared_cash_usd' => 75.00,
            'declared_pos_bs' => 1500.00,
            'notes' => 'Cierre normal con pequeño descuadre por cambio en sencillo.',
        ]);

        $this->assertEquals('closed', $closedShift->status);
        $this->assertNotNull($closedShift->closed_at);
        $this->assertEquals(700.00, $closedShift->system_cash_bs);
        $this->assertEquals(80.00, $closedShift->system_cash_usd);
        $this->assertEquals(1500.00, $closedShift->system_pos_bs);
        $this->assertEquals(720.00, $closedShift->declared_cash_bs);
        $this->assertEquals(75.00, $closedShift->declared_cash_usd);
        $this->assertEquals(20.00, $closedShift->difference_cash_bs); // Sobrante
        $this->assertEquals(-5.00, $closedShift->difference_cash_usd); // Faltante
    }
}
