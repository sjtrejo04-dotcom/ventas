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
     * @param  array<int, array<string, mixed>>  $items
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
