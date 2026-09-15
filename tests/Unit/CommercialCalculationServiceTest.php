<?php

declare(strict_types=1);

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
