# Lógica de Negocio (Business Logic) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar el 100% de la lógica de negocio para que el sistema de ventas esté completamente funcional (Backend de POS, Recursos Faltantes y Cierre de Caja).

**Architecture:** Se crearán los recursos de Filament faltantes para la gestión de datos básicos (Métodos de Pago, Tasa de Cambio). Luego se implementará el componente Livewire del POS (`App\Filament\Admin\Pages\Pos`) que enlaza la vista existente con la creación atómica de Ventas y Pagos. Finalmente se implementará el Cierre de Caja (Z-Report) para consolidar ingresos y egresos.

**Tech Stack:** Laravel 11, Filament v3, Livewire, Tailwind CSS.

**Spec:** N/A (Generado a partir del análisis del código actual).

## Global Constraints

- Sigue los estándares de código PSR-12.
- Utiliza declaración estricta de tipos `declare(strict_types=1);` en archivos nuevos.
- Maneja transacciones de base de datos (`DB::transaction`) cuando se creen múltiples registros dependientes (ej. Venta + Detalles + Pago).

---

### Task 1: Recursos de Gestión Básica (Exchange Rates & Payment Methods)

Para que el POS funcione, necesitamos configurar la Tasa BCV y los métodos de pago. Las migraciones ya existen.

**Files:**
- Create: `app/Filament/Admin/Resources/ExchangeRateResource.php`
- Create: `app/Filament/Admin/Resources/PaymentMethodResource.php`

**Interfaces:**
- Produces: Paneles de administración para que el usuario pueda registrar tasas de cambio y métodos de pago.

- [ ] **Step 1: Crear Recurso ExchangeRate**
Ejecutar el comando de Filament para generar el recurso basado en el modelo `ExchangeRate`.
```bash
php artisan make:filament-resource ExchangeRate --generate --panel=admin
```

- [ ] **Step 2: Configurar ExchangeRateResource**
Asegurar que el formulario tenga `date` y `rate`.
```php
// En app/Filament/Admin/Resources/ExchangeRateResource.php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\DatePicker::make('date')->required()->default(now()),
        Forms\Components\TextInput::make('rate')->numeric()->required(),
    ]);
}
```

- [ ] **Step 3: Crear Recurso PaymentMethod**
Ejecutar el comando para `PaymentMethod`.
```bash
php artisan make:filament-resource PaymentMethod --generate --panel=admin
```

- [ ] **Step 4: Configurar PaymentMethodResource**
Asegurar que el formulario tenga `name` y `is_active`.
```php
// En app/Filament/Admin/Resources/PaymentMethodResource.php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\TextInput::make('name')->required(),
        Forms\Components\Toggle::make('is_active')->default(true),
    ]);
}
```

- [ ] **Step 5: Commit**
```bash
git add app/Filament/Admin/Resources/ExchangeRateResource.php app/Filament/Admin/Resources/PaymentMethodResource.php
git commit -m "feat: add missing resources for exchange rates and payment methods"
```

### Task 2: Backend del Punto de Venta (POS Livewire Component)

La vista `pos.blade.php` existe, pero falta el controlador Livewire para darle vida.

**Files:**
- Create: `app/Filament/Admin/Pages/Pos.php`
- Modify: `resources/views/filament/admin/pages/pos.blade.php` (si es necesario enlazar variables).

**Interfaces:**
- Consumes: Modelos `Product`, `ExchangeRate`, `PaymentMethod`.
- Produces: Interfaz funcional que permite buscar productos, añadirlos al carrito, y procesar la venta.

- [ ] **Step 1: Crear la clase Pos**
Crear el archivo `app/Filament/Admin/Pages/Pos.php` extendiendo de `Filament\Pages\Page`.
```php
<?php
declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use App\Models\Product;
use App\Models\ExchangeRate;

class Pos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static string $view = 'filament.admin.pages.pos';
    
    public string $search = '';
    public float $bcvRate = 0.0;
    public array $cart = [];
    public float $totalBase = 0.0;
    
    public function mount(): void
    {
        $rate = ExchangeRate::latest('date')->first();
        $this->bcvRate = $rate ? (float) $rate->rate : 0.0;
    }
    
    // ... agregar mÃ©todos addToCart, removeFromCart, processSale
}
```

- [ ] **Step 3: Procesar Venta (Process Sale)**
Crear la transacción que guarda la Venta y sus Ítems.
```php
public function processSale(): void
{
    if (empty($this->cart)) return;
    
    \DB::transaction(function () {
        $sale = \App\Models\Sale::create([
            'date' => now(),
            'total_base' => $this->totalBase,
            'total_vat' => $this->totalBase * 0.16, // Asumiendo 16%
            'total_amount' => $this->totalBase * 1.16,
            'status' => 'completed',
        ]);
        
        foreach ($this->cart as $item) {
            $sale->items()->create([
                'product_id' => $item['id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
            ]);
        }
    });
    
    $this->cart = [];
    $this->calculateTotals();
    \Filament\Notifications\Notification::make()->title('Venta completada')->success()->send();
}
```

- [ ] **Step 4: Commit**
```bash
git add app/Filament/Admin/Pages/Pos.php
git commit -m "feat: implement POS backend logic"
```

### Task 3: Lógica de Cierre de Caja (Z-Report)

Crear el recurso para gestionar los Cortes Z diarios, calculando automáticamente las ventas del día.

**Files:**
- Create: `app/Filament/Admin/Resources/ZReportResource.php`

**Interfaces:**
- Consumes: Modelos `Sale`, `Expense`.
- Produces: Panel para generar el cierre diario.

- [ ] **Step 1: Crear Recurso ZReport**
```bash
php artisan make:filament-resource ZReport --generate --panel=admin
```

- [ ] **Step 2: Configurar Lógica de Cálculo en el Formulario**
Al crear un Z-Report, se deben precargar los totales del día actual.
```php
// En app/Filament/Admin/Resources/ZReportResource.php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\DatePicker::make('date')->default(now())->required(),
        Forms\Components\TextInput::make('total_sales')
            ->default(fn() => \App\Models\Sale::whereDate('date', today())->sum('total_amount')),
        Forms\Components\TextInput::make('total_expenses')
            ->default(fn() => \App\Models\Expense::whereDate('date', today())->sum('amount')),
        // ... otros campos como cash_in_drawer
    ]);
}
```

- [ ] **Step 3: Commit**
```bash
git add app/Filament/Admin/Resources/ZReportResource.php
git commit -m "feat: add Z-Report resource for daily closing"
```
