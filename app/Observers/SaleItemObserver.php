<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryMovement;
use App\Models\Sale;
use App\Models\SaleItem;

class SaleItemObserver
{
    public function created(SaleItem $saleItem): void
    {
        if ($saleItem->product) {
            InventoryMovement::create([
                'product_id' => $saleItem->product_id,
                'type' => 'out',
                'quantity' => $saleItem->quantity,
                'unit_cost' => $saleItem->unit_cost ?? 0,
                'concept' => 'Venta #'.$saleItem->sale_id,
                'reference_type' => (new Sale)->getMorphClass(),
                'reference_id' => $saleItem->sale_id,
            ]);
        }
    }

    public function updated(SaleItem $saleItem): void
    {
        if ($saleItem->product) {
            if ($saleItem->isDirty('quantity')) {
                $difference = (float) $saleItem->quantity - (float) $saleItem->getOriginal('quantity');

                if ($difference > 0) {
                    // More items sold, stock goes out
                    InventoryMovement::create([
                        'product_id' => $saleItem->product_id,
                        'type' => 'out',
                        'quantity' => $difference,
                        'unit_cost' => $saleItem->unit_cost ?? 0,
                        'concept' => 'Ajuste de Venta (+ cantidad) #'.$saleItem->sale_id,
                        'reference_type' => (new Sale)->getMorphClass(),
                        'reference_id' => $saleItem->sale_id,
                    ]);
                } elseif ($difference < 0) {
                    // Fewer items sold, stock comes back in
                    InventoryMovement::create([
                        'product_id' => $saleItem->product_id,
                        'type' => 'in',
                        'quantity' => abs($difference),
                        'unit_cost' => $saleItem->unit_cost ?? 0,
                        'concept' => 'Ajuste de Venta (- cantidad) #'.$saleItem->sale_id,
                        'reference_type' => (new Sale)->getMorphClass(),
                        'reference_id' => $saleItem->sale_id,
                    ]);
                }
            }
        }
    }

    public function deleted(SaleItem $saleItem): void
    {
        if ($saleItem->product) {
            InventoryMovement::create([
                'product_id' => $saleItem->product_id,
                'type' => 'in',
                'quantity' => $saleItem->quantity,
                'unit_cost' => $saleItem->unit_cost ?? 0,
                'concept' => 'Cancelación/Eliminación de Venta #'.$saleItem->sale_id,
                'reference_type' => (new Sale)->getMorphClass(),
                'reference_id' => $saleItem->sale_id,
            ]);
        }
    }
}
