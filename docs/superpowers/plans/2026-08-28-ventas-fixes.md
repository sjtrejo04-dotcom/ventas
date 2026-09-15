# Reparación de Errores Críticos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Solucionar los errores críticos identificados en la revisión de código (ExpensesTable roto, pérdida de datos por falta de campos fillables, deducción de inventario, y validación de totales).

**Architecture:** Mantenemos la estructura actual de Filament pero agregamos un Observer para manejar la lógica de negocio (deducción de inventario) y sobrescribimos el ciclo de vida del recurso para garantizar el cálculo seguro de los totales.

**Tech Stack:** PHP 8.3, Laravel 11, Filament v3

**Spec:** `C:/Users/santiago/.gemini/antigravity-cli/brain/071365fb-6149-407e-a19b-fca2d6f3c3dc/review-ventas-declaracion.md`

## Global Constraints

- Sigue los estándares de código PSR-12.
- Utiliza declaración estricta de tipos `declare(strict_types=1);` en archivos nuevos.

---

### Task 1: Fix ExpensesTable Resource

**Files:**
- Modify: `app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php:16-41`

**Interfaces:**
- Consumes: N/A
- Produces: Correct table columns for Expense resource.

- [ ] **Step 1: Update columns to match Expense model**

Reemplaza las columnas problemáticas en `app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php`:

```php
                TextColumn::make('provider.name')
                    ->label('Proveedor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->label('Nro. Factura')
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Monto Total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('expense_date')
                    ->label('Fecha de Gasto')
                    ->date()
                    ->sortable(),
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php
git commit -m "fix: update ExpensesTable columns to match Expense model fields"
```

---

### Task 2: Fix Missing Fillable Fields

**Files:**
- Modify: `app/Models/Sale.php:12-22`
- Modify: `app/Models/Expense.php:12-22`

**Interfaces:**
- Consumes: N/A
- Produces: Sale and Expense models that accept accounting dates.

- [ ] **Step 1: Add date fields to Expense fillable array**

En `app/Models/Expense.php`, agrega `'invoice_date'`, `'accounting_date'`, y `'due_date'` al array `$fillable`.

```php
    protected $fillable = [
        'user_id',
        'provider_id',
        'invoice_number',
        'control_number',
        'description',
        'total_base',
        'total_vat',
        'total_amount',
        'expense_date',
        'invoice_date',
        'accounting_date',
        'due_date',
    ];
```

- [ ] **Step 2: Add date fields to Sale fillable array**

En `app/Models/Sale.php`, agrega `'invoice_date'`, `'accounting_date'`, y `'due_date'` al array `$fillable`.

```php
    protected $fillable = [
        'user_id',
        'customer_id',
        'invoice_number',
        'total_base',
        'total_vat',
        'total_igtf',
        'total_amount',
        'status',
        'invoice_date',
        'accounting_date',
        'due_date',
    ];
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/Expense.php app/Models/Sale.php
git commit -m "fix: add missing accounting dates to fillable properties"
```

---

### Task 3: Implement Inventory Deduction

**Files:**
- Create: `app/Observers/SaleItemObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: `SaleItem` model creation.
- Produces: Deduction of `Product` stock.

- [ ] **Step 1: Create the Observer**

Ejecuta el comando para crear el Observer:
```bash
php artisan make:observer SaleItemObserver --model=SaleItem
```

- [ ] **Step 2: Implement stock deduction in created event**

Abre `app/Observers/SaleItemObserver.php` e implementa el método `created`:

```php
<?php

namespace App\Observers;

use App\Models\SaleItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class SaleItemObserver
{
    public function created(SaleItem $saleItem): void
    {
        DB::transaction(function () use ($saleItem) {
            $product = $saleItem->product;
            
            if ($product) {
                $product->stock -= $saleItem->quantity;
                $product->save();
            }
        });
    }
}
```

- [ ] **Step 3: Register the Observer**

Abre `app/Providers/AppServiceProvider.php` y regístralo en el método `boot`:

```php
use App\Models\SaleItem;
use App\Observers\SaleItemObserver;

// Dentro del método boot():
public function boot(): void
{
    SaleItem::observe(SaleItemObserver::class);
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Observers/SaleItemObserver.php app/Providers/AppServiceProvider.php
git commit -m "feat: implement SaleItemObserver to deduct product stock on sale"
```

---

### Task 4: Secure Sale Totals Calculation

**Files:**
- Modify: `app/Filament/Admin/Resources/Sales/Pages/CreateSale.php`

**Interfaces:**
- Consumes: Form data before creation.
- Produces: Re-calculated totals based on repeater items.

- [ ] **Step 1: Override mutateFormDataBeforeCreate**

En `app/Filament/Admin/Resources/Sales/Pages/CreateSale.php`, agrega el método `mutateFormDataBeforeCreate` para calcular matemáticamente los totales reales desde el backend:

```php
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $totalBase = 0;
        $totalVat = 0;

        if (isset($data['saleItems']) && is_array($data['saleItems'])) {
            foreach ($data['saleItems'] as $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                
                $lineTotal = $quantity * $unitPrice;
                $totalBase += $lineTotal;
                
                // Cálculo simple de IVA, asumiendo 16% si aplica
                $totalVat += $lineTotal * 0.16; 
            }
        }

        $data['total_base'] = $totalBase;
        $data['total_vat'] = $totalVat;
        $data['total_amount'] = $totalBase + $totalVat + (float) ($data['total_igtf'] ?? 0);

        return $data;
    }
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Admin/Resources/Sales/Pages/CreateSale.php
git commit -m "feat: securely calculate sale totals in backend before create"
```
