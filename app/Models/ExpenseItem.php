<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\CommercialCalculationService;
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

    protected static function booted(): void
    {
        static::creating(function (ExpenseItem $item) {
            if ($item->subtotal === null || (float) $item->subtotal <= 0) {
                $item->subtotal = CommercialCalculationService::calculateItemSubtotal((float) $item->quantity, (float) $item->unit_cost);
            }
            if ($item->vat_amount === null) {
                $item->vat_amount = CommercialCalculationService::calculateItemVat((float) $item->subtotal, (bool) $item->has_vat);
            }
            if ($item->selling_price === null || (float) $item->selling_price <= 0) {
                $item->selling_price = CommercialCalculationService::calculateSellingPrice((float) $item->unit_cost, (float) $item->margin_percent);
            }
        });
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
