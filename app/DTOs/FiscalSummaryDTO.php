<?php

declare(strict_types=1);

namespace App\DTOs;

class FiscalSummaryDTO
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $exempt_amount,
        public readonly float $taxable_base,
        public readonly float $vat_amount,
        public readonly float $igtf_base,
        public readonly float $igtf_amount,
        public readonly float $total_amount_bs,
        public readonly float $total_amount_usd,
        public readonly float $change_due_bs,
        public readonly float $change_due_usd,
        public readonly float $subtotal_bs = 0.0,
        public readonly float $exempt_amount_bs = 0.0,
        public readonly float $taxable_base_bs = 0.0,
        public readonly float $vat_amount_bs = 0.0,
        public readonly float $igtf_base_bs = 0.0,
        public readonly float $igtf_amount_bs = 0.0,
    ) {}

    /**
     * Convert the DTO to an array.
     *
     * @return array<string, float>
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'exempt_amount' => $this->exempt_amount,
            'taxable_base' => $this->taxable_base,
            'vat_amount' => $this->vat_amount,
            'igtf_base' => $this->igtf_base,
            'igtf_amount' => $this->igtf_amount,
            'total_amount_bs' => $this->total_amount_bs,
            'total_amount_usd' => $this->total_amount_usd,
            'change_due_bs' => $this->change_due_bs,
            'change_due_usd' => $this->change_due_usd,
            'subtotal_bs' => $this->subtotal_bs,
            'exempt_amount_bs' => $this->exempt_amount_bs,
            'taxable_base_bs' => $this->taxable_base_bs,
            'vat_amount_bs' => $this->vat_amount_bs,
            'igtf_base_bs' => $this->igtf_base_bs,
            'igtf_amount_bs' => $this->igtf_amount_bs,
        ];
    }
}
