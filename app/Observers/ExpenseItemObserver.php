<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\InventoryMovement;

class ExpenseItemObserver
{
    public function created(ExpenseItem $item): void
    {
        $product = $item->product;
        if (! $product) {
            return;
        }

        // 1. Update product cost and selling price with replacement values
        $product->update([
            'cost' => $item->unit_cost,
            'price' => $item->selling_price,
        ]);

        // 2. Record Kardex movement (this automatically triggers InventoryMovementObserver to increment product.stock)
        $expense = $item->expense;
        $providerName = $expense?->provider?->name ?? 'Proveedor';
        $invoiceNumber = $expense?->invoice_number ?? '';

        InventoryMovement::create([
            'product_id' => $item->product_id,
            'user_id' => $expense?->user_id ?? auth()->id(),
            'type' => 'in',
            'concept' => trim("Compra Proveedor: {$providerName} - Factura #{$invoiceNumber}"),
            'quantity' => $item->quantity,
            'unit_cost' => $item->unit_cost,
            'reference_type' => (new Expense)->getMorphClass(),
            'reference_id' => $item->expense_id,
        ]);
    }
}
