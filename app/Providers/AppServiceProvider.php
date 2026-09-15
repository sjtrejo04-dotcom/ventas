<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\ExpenseItem;
use App\Models\InventoryMovement;
use App\Models\SaleItem;
use App\Observers\ExpenseItemObserver;
use App\Observers\InventoryMovementObserver;
use App\Observers\SaleItemObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SaleItem::observe(SaleItemObserver::class);
        InventoryMovement::observe(InventoryMovementObserver::class);
        ExpenseItem::observe(ExpenseItemObserver::class);
    }
}
