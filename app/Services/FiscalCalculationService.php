<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\FiscalSummaryDTO;
use App\Models\PaymentMethod;
use App\Models\Product;

class FiscalCalculationService
{
    public const VAT_RATE = 0.16;

    public const IGTF_RATE = 0.03;

    /**
     * Calculate fiscal breakdown, taxes (IVA, IGTF bimoneda), totals and change due.
     *
    /**
     * Calculate fiscal breakdown, taxes (IVA for Bs, IGTF for cash USD), totals and change due.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $payments
     */
    public static function calculate(
        array $items,
        array $payments = [],
        float $bcvRate = 1.0,
        ?string $intendedMethod = null
    ): FiscalSummaryDTO {
        $rate = $bcvRate > 0 ? $bcvRate : 1.0;

        $rawTaxableBase = 0.0;
        $exemptAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? $item['qty'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? $item['price'] ?? 0);

            $subtotalItem = isset($item['subtotal'])
                ? (float) $item['subtotal']
                : round($quantity * $unitPrice, 2);

            $hasVat = self::determineIfItemHasVat($item);

            if ($hasVat) {
                $rawTaxableBase += $subtotalItem;
            } else {
                $exemptAmount += $subtotalItem;
            }
        }

        $rawTaxableBase = round($rawTaxableBase, 2);
        $exemptAmount = round($exemptAmount, 2);
        $subtotal = round($rawTaxableBase + $exemptAmount, 2);

        // Process recorded payments
        $usdCashPaid = 0.0;
        $usdTotalPaid = 0.0;
        $totalPaidUsd = 0.0;
        $totalPaidBs = 0.0;
        $usdPaymentsCount = 0;
        $bsPaymentsCount = 0;

        foreach ($payments as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $currency = self::determinePaymentCurrency($payment);
            $appliesIgtf = self::determineIfPaymentAppliesIgtf($payment, $currency);

            if ($currency === 'USD') {
                $amountUsd = $amount;
                $amountBs = round($amount * $rate, 2);
                $usdPaymentsCount++;
                $usdTotalPaid += $amountUsd;

                if ($appliesIgtf) {
                    $usdCashPaid += $amountUsd;
                }
            } else {
                $amountBs = $amount;
                $amountUsd = round($amount / $rate, 2);
                $bsPaymentsCount++;
            }

            $totalPaidUsd += $amountUsd;
            $totalPaidBs += $amountBs;
        }

        $taxableBase = $rawTaxableBase;

        // 1. IVA (16%): Conforme a la Ley del IVA y al modelo fiscal SENIAT (PlanSuárez),
        // el IVA grava la venta y es exigible al 16% pleno sobre la base gravada,
        // sin exoneración ni reducción por pagar en moneda extranjera.
        $vatAmount = round($taxableBase * self::VAT_RATE, 2);
        $subtotalWithVat = round($subtotal + $vatAmount, 2);

        // 2. IGTF (3%): Impuesto adicional sobre el monto pagado en efectivo divisas.
        if (! empty($payments)) {
            // Aplica sobre el efectivo en divisas efectivamente pagado
            $igtfBase = min(round($usdCashPaid, 2), $subtotalWithVat);
            $igtfAmount = round($igtfBase * self::IGTF_RATE, 2);
        } else {
            // Proyección inicial cuando no se han registrado pagos aún:
            $isCashUsdIntended = $intendedMethod !== null && (
                in_array($intendedMethod, ['cash_usd', 'usd', 'divisa', 'efectivo divisas ($)'], true) ||
                (str_contains(strtolower($intendedMethod), 'cash') && str_contains(strtolower($intendedMethod), 'usd')) ||
                (str_contains(strtolower($intendedMethod), 'efectivo') && str_contains(strtolower($intendedMethod), 'divisa'))
            );

            if ($isCashUsdIntended) {
                $igtfBase = $subtotalWithVat;
                $igtfAmount = round($igtfBase * self::IGTF_RATE, 2);
            } else {
                $igtfBase = 0.0;
                $igtfAmount = 0.0;
            }
        }

        $totalAmountUsd = round($subtotalWithVat + $igtfAmount, 2);
        $totalAmountBs = round($totalAmountUsd * $rate, 2);

        // Calculate change due (vuelto)
        $changeDueUsd = 0.0;
        $changeDueBs = 0.0;

        if ($totalPaidUsd > $totalAmountUsd) {
            $changeDueUsd = round($totalPaidUsd - $totalAmountUsd, 2);
            $changeDueBs = round($changeDueUsd * $rate, 2);
        } elseif ($totalPaidBs > $totalAmountBs) {
            $changeDueBs = round($totalPaidBs - $totalAmountBs, 2);
            $changeDueUsd = round($changeDueBs / $rate, 2);
        }

        return new FiscalSummaryDTO(
            subtotal: $subtotal,
            exempt_amount: $exemptAmount,
            taxable_base: $taxableBase,
            vat_amount: $vatAmount,
            igtf_base: $igtfBase,
            igtf_amount: $igtfAmount,
            total_amount_bs: $totalAmountBs,
            total_amount_usd: $totalAmountUsd,
            change_due_bs: $changeDueBs,
            change_due_usd: $changeDueUsd,
            subtotal_bs: round($subtotal * $rate, 2),
            exempt_amount_bs: round($exemptAmount * $rate, 2),
            taxable_base_bs: round($taxableBase * $rate, 2),
            vat_amount_bs: round($vatAmount * $rate, 2),
            igtf_base_bs: round($igtfBase * $rate, 2),
            igtf_amount_bs: round($igtfAmount * $rate, 2),
        );
    }

    private static function determineIfItemHasVat(array $item): bool
    {
        if (isset($item['has_vat'])) {
            return (bool) $item['has_vat'];
        }

        if (isset($item['is_exempt'])) {
            return ! ((bool) $item['is_exempt']);
        }

        if (isset($item['tax_type'])) {
            $type = strtoupper((string) $item['tax_type']);
            if ($type === 'E' || $type === 'EXEMPT') {
                return false;
            }
            if ($type === 'G' || $type === 'GRAVABLE') {
                return true;
            }
        }

        if (isset($item['vat_amount']) && (float) $item['vat_amount'] > 0) {
            return true;
        }

        if (! empty($item['product_id'])) {
            $product = Product::find($item['product_id']);
            if ($product) {
                return (bool) $product->has_vat;
            }
        }

        return false;
    }

    private static function determinePaymentCurrency(array $payment): string
    {
        if (! empty($payment['currency'])) {
            $cur = strtoupper(trim((string) $payment['currency']));
            if (in_array($cur, ['USD', '$'], true)) {
                return 'USD';
            }
            if (in_array($cur, ['BS', 'VES', 'VED'], true)) {
                return 'VES';
            }
        }

        $method = strtolower(trim((string) ($payment['method'] ?? '')));

        if (str_contains($method, 'usd') || str_contains($method, 'divisa') || str_contains($method, 'zelle') || str_contains($method, '$')) {
            return 'USD';
        }

        if (str_contains($method, 'bs') || str_contains($method, 'bolivar') || str_contains($method, 'pos') || str_contains($method, 'debito') || str_contains($method, 'movil') || str_contains($method, 'cashea')) {
            return 'VES';
        }

        return 'USD';
    }

    private static function determineIfPaymentAppliesIgtf(array $payment, string $currency): bool
    {
        if (isset($payment['applies_igtf'])) {
            return (bool) $payment['applies_igtf'];
        }

        if (! empty($payment['payment_method_id'])) {
            $pm = PaymentMethod::find($payment['payment_method_id']);
            if ($pm) {
                return (bool) $pm->applies_igtf;
            }
        }

        $method = strtolower(trim((string) ($payment['method'] ?? '')));

        // Rule: Only foreign currency cash payments apply IGTF
        if ($currency === 'USD') {
            if (in_array($method, ['cash_usd', 'efectivo_usd', 'efectivo divisas ($)', 'divisas_efectivo', 'cash', 'efectivo'], true)) {
                return true;
            }
            if (str_contains($method, 'efectivo') && (str_contains($method, 'divisa') || str_contains($method, '$') || str_contains($method, 'usd'))) {
                return true;
            }
            if (str_contains($method, 'cash') && str_contains($method, 'usd')) {
                return true;
            }
        }

        return false;
    }
}
