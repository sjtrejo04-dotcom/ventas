<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FiscalCalculationService;
use Tests\TestCase;

class FiscalCalculationServiceTest extends TestCase
{
    public function test_single_currency_bolivares_payment_has_zero_igtf(): void
    {
        // Case: Monitor $100 + 16% VAT = $116 USD. BCV Rate: 50 Bs/USD.
        // Customer pays 100% in Bolívares (Punto de Venta / Débito + Cashea)
        $items = [
            [
                'name' => 'Monitor Estándar 32"',
                'quantity' => 1,
                'unit_price' => 100.00,
                'has_vat' => true,
            ],
        ];

        $payments = [
            [
                'method' => 'pos_bs',
                'amount' => 2900.00, // 50% in Bs at rate 50 = $58
                'currency' => 'VES',
            ],
            [
                'method' => 'cashea',
                'amount' => 2900.00, // 50% in Bs at rate 50 = $58
                'currency' => 'VES',
            ],
        ];

        $summary = FiscalCalculationService::calculate($items, $payments, bcvRate: 50.0);

        $this->assertEquals(100.00, $summary->subtotal);
        $this->assertEquals(100.00, $summary->taxable_base);
        $this->assertEquals(0.00, $summary->exempt_amount);
        $this->assertEquals(16.00, $summary->vat_amount);
        $this->assertEquals(0.00, $summary->igtf_base);
        $this->assertEquals(0.00, $summary->igtf_amount);
        $this->assertEquals(116.00, $summary->total_amount_usd);
        $this->assertEquals(5800.00, $summary->total_amount_bs);
        $this->assertEquals(0.00, $summary->change_due_usd);
        $this->assertEquals(0.00, $summary->change_due_bs);
    }

    public function test_foreign_currency_cash_payment_applies_full_vat_and_3_percent_igtf_add_on(): void
    {
        // Case: Total purchase $100 (taxable) + 16% VAT = $116.00 USD.
        // Rule: IVA is full 16% ($16.00). Cash USD adds 3% IGTF ($116.00 * 0.03 = $3.48).
        // Total amount = $119.48 USD.
        $items = [
            [
                'name' => 'Canasta Familiar',
                'quantity' => 1,
                'unit_price' => 100.00,
                'has_vat' => true,
            ],
        ];

        $payments = [
            [
                'method' => 'cash_usd',
                'amount' => 119.48,
                'currency' => 'USD',
            ],
        ];

        $summary = FiscalCalculationService::calculate($items, $payments, bcvRate: 40.0);

        $this->assertEquals(100.00, $summary->taxable_base);
        $this->assertEquals(16.00, $summary->vat_amount);
        $this->assertEquals(116.00, $summary->igtf_base);
        $this->assertEquals(3.48, $summary->igtf_amount);
        $this->assertEquals(119.48, $summary->total_amount_usd);
        $this->assertEquals(4779.20, $summary->total_amount_bs); // 119.48 * 40
    }

    public function test_mixed_payment_retains_full_vat_and_applies_igtf_strictly_to_cash_usd_portion(): void
    {
        // Purchase $100.00 taxable.
        // Full 16% VAT applies to taxable base = $16.00 USD.
        // Paid $50 USD cash -> generates $1.50 IGTF (3% on $50 USD cash).
        // Total = $100 base + $16 VAT + $1.50 IGTF = $117.50 USD.
        // Paid rest in Bs (at rate 50): $67.50 USD * 50 = 3,375.00 Bs.
        $items = [
            [
                'name' => 'Combo Familiar',
                'quantity' => 1,
                'unit_price' => 100.00,
                'has_vat' => true,
            ],
        ];

        $payments = [
            [
                'method' => 'cash_usd',
                'amount' => 50.00,
                'currency' => 'USD',
            ],
            [
                'method' => 'pos_bs',
                'amount' => 3375.00, // $67.50 USD equivalent at rate 50
                'currency' => 'VES',
            ],
        ];

        $summary = FiscalCalculationService::calculate($items, $payments, bcvRate: 50.0);

        $this->assertEquals(100.00, $summary->taxable_base);
        $this->assertEquals(16.00, $summary->vat_amount);
        $this->assertEquals(50.00, $summary->igtf_base);
        $this->assertEquals(1.50, $summary->igtf_amount);
        $this->assertEquals(117.50, $summary->total_amount_usd);
        $this->assertEquals(5875.00, $summary->total_amount_bs);
    }

    public function test_plansuarez_real_fiscal_invoice_numbers_match_exactly(): void
    {
        // Real SENIAT Invoice from PlanSuarez C.A. (media_1788741699791.jpg)
        // Exempt: Bs 16,715.11
        // Taxable Base: Bs 7,301.10
        // VAT (16%): Bs 1,168.18 (7,301.10 * 0.16)
        // Paid EFE./DIV. (cash USD equivalent): Bs 7,913.20
        // Paid T/Debito (Bolívares debit card): Bs 17,508.59
        // IGTF 3%: Bs 237.40 (7,913.20 * 0.03)
        // Monto A Pagar: Bs 25,421.79
        // B.I./IGTF: Bs 7,913.20
        $items = [
            ['name' => 'Queso y Milanesa (Exento)', 'quantity' => 1, 'unit_price' => 16715.11, 'has_vat' => false],
            ['name' => 'Papel y Servilletas (Gravable)', 'quantity' => 1, 'unit_price' => 7301.10, 'has_vat' => true],
        ];

        $payments = [
            ['method' => 'cash_usd', 'amount' => 7913.20, 'currency' => 'USD', 'applies_igtf' => true],
            ['method' => 'pos_bs', 'amount' => 17508.59, 'currency' => 'VES', 'applies_igtf' => false],
        ];

        $summary = FiscalCalculationService::calculate($items, $payments, bcvRate: 1.0);

        $this->assertEquals(16715.11, $summary->exempt_amount);
        $this->assertEquals(7301.10, $summary->taxable_base);
        $this->assertEquals(1168.18, $summary->vat_amount);
        $this->assertEquals(7913.20, $summary->igtf_base);
        $this->assertEquals(237.40, $summary->igtf_amount);
        $this->assertEquals(25421.79, $summary->total_amount_bs);
        $this->assertEquals(25421.79, $summary->total_amount_usd);
    }

    public function test_mixed_items_exempt_and_taxable_separation(): void
    {
        // Item 1: Harina de Maíz (Exempt) 2 units * $1.50 = $3.00
        // Item 2: Refresco 2L (Gravable) 1 unit * $2.00 = $2.00 + $0.32 VAT
        $items = [
            [
                'name' => 'Harina de Maíz 1kg',
                'quantity' => 2,
                'unit_price' => 1.50,
                'has_vat' => false,
            ],
            [
                'name' => 'Refresco 2L',
                'quantity' => 1,
                'unit_price' => 2.00,
                'has_vat' => true,
            ],
        ];

        $summary = FiscalCalculationService::calculate($items, [], bcvRate: 40.0);

        $this->assertEquals(5.00, $summary->subtotal);
        $this->assertEquals(3.00, $summary->exempt_amount);
        $this->assertEquals(2.00, $summary->taxable_base);
        $this->assertEquals(0.32, $summary->vat_amount);
        $this->assertEquals(5.32, $summary->total_amount_usd);
        $this->assertEquals(212.80, $summary->total_amount_bs);
    }

    public function test_change_due_calculation_in_usd_and_bs(): void
    {
        // Sale of $40 (exempt) with rate 40 Bs/USD. Total = $40 = 1600 Bs.
        // Customer gives a $50 USD bill.
        // Since payment is cash USD, IGTF applies to the $40 invoice amount:
        // IGTF = $40 * 0.03 = $1.20 USD.
        // Total amount = $41.20 USD.
        // Paid = $50.00 USD.
        // Change = $50.00 - $41.20 = $8.80 USD.
        // Change in Bs = 8.80 * 40 = 352.00 Bs.
        $items = [
            [
                'name' => 'Medicina Exenta',
                'quantity' => 1,
                'unit_price' => 40.00,
                'has_vat' => false,
            ],
        ];

        $payments = [
            [
                'method' => 'cash_usd',
                'amount' => 50.00,
                'currency' => 'USD',
            ],
        ];

        $summary = FiscalCalculationService::calculate($items, $payments, bcvRate: 40.0);

        $this->assertEquals(40.00, $summary->exempt_amount);
        $this->assertEquals(40.00, $summary->igtf_base);
        $this->assertEquals(1.20, $summary->igtf_amount);
        $this->assertEquals(41.20, $summary->total_amount_usd);
        $this->assertEquals(8.80, $summary->change_due_usd);
        $this->assertEquals(352.00, $summary->change_due_bs);
    }
}
