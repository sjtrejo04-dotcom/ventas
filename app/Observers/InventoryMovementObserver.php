<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryMovement;

class InventoryMovementObserver
{
    public function creating(InventoryMovement $movement): void
    {
        $product = $movement->product;

        if ($movement->user_id === null && auth()->check()) {
            $movement->user_id = auth()->id();
        }

        if ($movement->unit_cost === null) {
            $movement->unit_cost = (float) ($product?->cost ?? 0);
        }

        if (empty($movement->concept)) {
            $movement->concept = 'Movimiento manual de inventario';
        }

        if ($movement->stock_after_movement === null) {
            $currentStock = (float) ($product?->stock ?? 0);
            if ($movement->type === 'in') {
                $movement->stock_after_movement = $currentStock + (float) $movement->quantity;
            } elseif ($movement->type === 'out') {
                $movement->stock_after_movement = max(0, $currentStock - (float) $movement->quantity);
            } elseif ($movement->type === 'adjustment') {
                $movement->stock_after_movement = (float) $movement->quantity;
            } else {
                $movement->stock_after_movement = $currentStock;
            }
        }
    }

    public function created(InventoryMovement $movement): void
    {
        $product = $movement->product;

        if (! $product) {
            return;
        }

        if ($movement->type === 'in') {
            $product->increment('stock', $movement->quantity);
        } elseif ($movement->type === 'out') {
            $product->decrement('stock', $movement->quantity);
        } elseif ($movement->type === 'adjustment') {
            $product->update(['stock' => $movement->quantity]);
        }
    }
}
