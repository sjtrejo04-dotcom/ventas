# Especificación de Diseño: Compras a Proveedores, Formación de Precios y Control de Inventario

**Fecha:** 2026-09-06  
**Proyecto:** Ventas Declaración (Laravel 13 + FilamentPHP v4 + Supabase PostgreSQL)  
**Estado:** Aprobado para Planificación  

---

## 1. Resumen Ejecutivo y Objetivos

El objetivo de este diseño es cerrar el ciclo comercial y operativo de la empresa conectando el subsistema de proveedores (**`providers`** y **`expenses`**) con el catálogo de productos (**`products`**), el Kardex de inventario (**`inventory_movements`**) y el motor de liquidación tributaria (Libro de Compras y Crédito Fiscal de IVA).

El usuario podrá registrar facturas de distribuidores detallando los productos adquiridos al costo neto real pagado, definir el margen de ganancia comercial deseado para calcular el precio de venta al público (PVP), dar entrada automática al stock en almacén, y elegir la condición de pago (Contado o Crédito con vencimiento).

### Objetivos Clave:
1. **Evolución del Módulo de Gastos a Compras & Proveedores**:
   - Reutilizar la tabla existente `expenses` añadiendo condición de pago (`payment_status`: `'paid'`, `'pending'`) y método de pago (`payment_method_id`).
   - Crear la tabla hija `expense_items` (renglones de mercancía), espejo funcional de `sale_items`.
2. **Matemática Financiera de Costo y Margen Comercial**:
   - Registrar el **Costo Unitario (`unit_cost`)** exacto pagado al proveedor en factura.
   - Implementar la fórmula de **Margen Comercial sobre Ventas (Margin)** estándar de la bóveda:
     $$\text{selling\_price} = \frac{\text{unit\_cost}}{1 - \frac{\text{margin\_percent}}{100}}$$
   - Actualizar el costo de reposición y el precio de venta en la ficha del producto (`products.cost` y `products.price`).
3. **Automatización del Kardex e Inventario**:
   - Al registrar la compra, generar automáticamente un `InventoryMovement` con `type: 'in'`, concepto descriptivo y referencia polimórfica a la factura (`reference_type: App\Models\Expense`, `reference_id: expense_id`).
   - Incrementar automáticamente las existencias en almacén (`products.stock`) mediante el observador existente.
4. **Cumplimiento Tributario SENIAT**:
   - Manejar estrictamente las alícuotas binarias **(G)** 16% y **(E)** Exento en cada renglón.
   - Alimentar el **Crédito Fiscal de IVA** para compensar contra el Débito Fiscal de ventas en la Declaración de Impuestos (`/admin/tax-report`) y generar el Libro de Compras.
5. **Creación Ágil de Productos Nuevos en Caliente**:
   - Permitir registrar un producto nuevo directamente desde la misma pantalla de compra sin abandonar el flujo.

---

## 2. Arquitectura del Sistema

```
+-----------------------------------------------------------------------------+
|                          PANEL ADMINISTRATIVO FILAMENT                      |
+-----------------------------------------------------------------------------+
|  [Filament Resource] ExpenseResource (Compras & Gastos a Proveedores)        |
|  - Activado en menú lateral: 'Finanzas & Contabilidad'                      |
|  - Formulario con cabecera fiscal, condición Contado/Crédito                |
|  - Repeater reactivo de ítems: Producto, Cantidad, Costo, Margen %, PVP     |
|  - Modal en caliente para dar de alta productos nuevos                      |
+-----------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------+
|                         CAPA DE NEGOCIO Y MODELOS                           |
+-----------------------------------------------------------------------------+
|  • Provider (Distribuidor con RIF y contacto)                               |
|  • Expense (Factura de compra / cabecera fiscal)                            |
|  • ExpenseItem (Renglones de compra con costo, margen y alícuota G/E)       |
|  • ExpenseItemObserver (Disparador de movimientos y actualización de costos)|
|  • InventoryMovementObserver (Incrementa products.stock al recibir 'in')    |
|  • Product (Catálogo con costo de reposición, PVP y stock actualizado)      |
+-----------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------+
|                     BASE DE DATOS EN LA NUBE (SUPABASE)                     |
|  - Tablas: providers, expenses, expense_items, inventory_movements, products|
+-----------------------------------------------------------------------------+
```

---

## 3. Modelo de Datos y Migraciones

### A. Modificación a la tabla existente `expenses`
Se añadirá una migración para incorporar los campos de condición de pago y monto exento:
```sql
ALTER TABLE expenses 
  ADD COLUMN payment_status VARCHAR(20) DEFAULT 'paid', -- 'paid' (contado), 'pending' (crédito)
  ADD COLUMN payment_method_id BIGINT NULL REFERENCES payment_methods(id) ON DELETE SET NULL,
  ADD COLUMN total_exempt NUMERIC(15, 2) DEFAULT 0.00;
```

### B. Creación de la tabla `expense_items` (Vínculo Directo entre Factura y Productos)
> **Aclaratoria de Arquitectura:** `expense_items` **NO** es una tabla de productos nuevos ni duplica la tabla `products`. Es la **tabla intermedia (pivote / renglón)** que vincula una factura de compra directamente con el producto existente en `products` (`product_id`), exactamente igual a como `sale_items` vincula las ventas con los productos.
> 
> En la interfaz, el usuario **NO crea otro producto**: simplemente selecciona el producto que ya existe en la tabla `products`, e indica cuántas unidades compró y a qué costo unitario vino en esa factura.

```sql
CREATE TABLE expense_items (
    id BIGSERIAL PRIMARY KEY,
    expense_id BIGINT NOT NULL REFERENCES expenses(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE RESTRICT, -- Link directo a products
    quantity NUMERIC(15, 2) NOT NULL DEFAULT 1.00,
    unit_cost NUMERIC(15, 2) NOT NULL, -- Costo neto pagado al distribuidor
    margin_percent NUMERIC(5, 2) NOT NULL DEFAULT 30.00, -- Margen de ganancia
    selling_price NUMERIC(15, 2) NOT NULL, -- Precio sugerido/calculado
    has_vat BOOLEAN NOT NULL DEFAULT true, -- true = (G) 16%, false = (E) Exento
    subtotal NUMERIC(15, 2) NOT NULL, -- quantity * unit_cost
    vat_amount NUMERIC(15, 2) NOT NULL DEFAULT 0.00, -- subtotal * 0.16 si has_vat
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE INDEX idx_expense_items_expense_id ON expense_items(expense_id);
CREATE INDEX idx_expense_items_product_id ON expense_items(product_id);
```

### C. Relaciones Eloquent
- **`App\Models\Expense`**:
  - `items(): HasMany` $\rightarrow$ `ExpenseItem::class`
  - `products(): BelongsToMany` $\rightarrow$ `Product::class` (vía `expense_items` con campos pivote)
  - `paymentMethod(): BelongsTo` $\rightarrow$ `PaymentMethod::class`
- **`App\Models\ExpenseItem`**:
  - `expense(): BelongsTo` $\rightarrow$ `Expense::class`
  - `product(): BelongsTo` $\rightarrow$ `Product::class`
- **`App\Models\Product`**:
  - `expenseItems(): HasMany` $\rightarrow$ `ExpenseItem::class`
  - `expenses(): BelongsToMany` $\rightarrow$ `Expense::class` (vía `expense_items`)


---

## 4. Lógica de Matemática Financiera y Tributaria

### A. Formación del Precio de Venta (Margen Comercial sobre Venta)
Para evitar la distorsión del simple markup, el sistema utiliza la fórmula contable de la bóveda:
$$\text{selling\_price} = \text{round}\left(\frac{\text{unit\_cost}}{1 - \frac{\text{margin\_percent}}{100}}, 2\right)$$

*Ejemplo numérico*:
- Costo proveedor: **\$10.00**
- Margen deseado: **30%**
- Precio de venta al público: $\frac{\$10.00}{1 - 0.30} = \frac{\$10.00}{0.70} = \mathbf{\$14.29}$
- Ganancia bruta unitaria: $\$14.29 - \$10.00 = \$4.29$ (representa exactamente el $30\%$ de $\$14.29$).

### B. Liquidación de la Factura de Compra
Para cada renglón:
- $\text{subtotal} = \text{round}(\text{quantity} \times \text{unit\_cost}, 2)$
- $\text{vat\_amount} = \text{has\_vat} \ ? \ \text{round}(\text{subtotal} \times 0.16, 2) : 0.00$

Para la cabecera `expenses`:
- $\text{total\_base} = \sum \text{subtotal de ítems con has\_vat = true}$
- $\text{total\_exempt} = \sum \text{subtotal de ítems con has\_vat = false}$
- $\text{total\_vat} = \sum \text{vat\_amount}$
- $\text{total\_amount} = \text{total\_base} + \text{total\_exempt} + \text{total\_vat}$

---

## 5. Automatización del Kardex y Catálogo (`ExpenseItemObserver`)

Al crearse o modificarse un `ExpenseItem`:
1. **Actualización de la ficha del producto (`Product`)**:
   - `$product->cost = $expenseItem->unit_cost;` (Costo de reposición actualizado).
   - `$product->price = $expenseItem->selling_price;` (Nuevo PVP listo para el POS).
   - `$product->save();`
2. **Generación del movimiento en Kardex (`InventoryMovement`)**:
   ```php
   InventoryMovement::create([
       'product_id' => $expenseItem->product_id,
       'user_id' => $expenseItem->expense->user_id,
       'type' => 'in',
       'concept' => 'Compra Proveedor: ' . $expenseItem->expense->provider->name . ' - Factura #' . $expenseItem->expense->invoice_number,
       'quantity' => $expenseItem->quantity,
       'unit_cost' => $expenseItem->unit_cost,
       'reference_type' => Expense::class,
       'reference_id' => $expenseItem->expense_id,
   ]);
   ```
3. **Disparador en cascada (`InventoryMovementObserver`)**:
   - Al insertarse el movimiento con `type: 'in'`, el observador existente ejecuta `$product->increment('stock', $movement->quantity);`, actualizando las existencias disponibles de inmediato.

---

## 6. Interfaz de Usuario en Filament Admin

### A. Recurso `ExpenseResource`
- `protected static bool $shouldRegisterNavigation = true;`
- Grupo de navegación: **Finanzas & Contabilidad** (o **Ventas & Facturación**).
- Icono: `heroicon-o-truck`.
- Título: **Compras a Proveedores**.

### B. Formulario `ExpenseForm`
1. **Sección Proveedor & Factura Fiscal**:
   - `provider_id`: Selector con búsqueda y `createOptionForm` para nuevos proveedores.
   - `invoice_number`: Texto obligatorio (N° Factura del distribuidor).
   - `control_number`: Texto obligatorio (N° Control fiscal).
   - `invoice_date`: Selector de fecha de factura.
   - `payment_status`: Radio o Toggle: `Contado` vs `A Crédito`.
   - `payment_method_id`: Visible si `payment_status == 'paid'`.
   - `due_date`: Visible si `payment_status == 'pending'` (Fecha de vencimiento para cuentas por pagar).
2. **Sección Renglones de Mercancía (Repeater `items`)**:
   - `product_id`: Selector con autocompletado y botón rápido `+ Crear Producto` (nombre, descripción, alícuota G/E).
   - `quantity`: Numérico $\ge 1$.
   - `unit_cost`: Numérico con el costo pagado al distribuidor.
   - `margin_percent`: Numérico (default `30.00`).
   - `selling_price`: Numérico reactivo calculado automáticamente con la fórmula comercial, editable por el usuario.
   - `has_vat`: Boolean toggle / select ((G) 16% o (E) Exento).
   - `subtotal`: Numérico de sólo lectura calculado en tiempo real.
3. **Sección Totales**:
   - Visualización reactiva en vivo de `total_base`, `total_exempt`, `total_vat` y `total_amount`.

---

## 7. Plan de Verificación y Pruebas

1. **Pruebas Unitarias (`tests/Unit/CommercialMarginCalculationTest.php`)**:
   - Verificar la precisión de la fórmula de margen comercial sobre ventas frente a markup.
   - Validar el redondeo simétrico a 2 decimales para evitar diferencias fiscales.
2. **Pruebas de Integración (`tests/Feature/ExpenseInventoryIntegrationTest.php`)**:
   - Crear una factura de compra con 2 ítems (uno Gravado y uno Exento).
   - Verificar que se creen los 2 registros en `expense_items`.
   - Verificar que se generen 2 movimientos en `inventory_movements` con `type: 'in'` y referencia a `Expense`.
   - Verificar que el stock de los productos aumente exactamente en las cantidades compradas.
   - Verificar que `cost` y `price` de los productos se actualicen con el costo de la factura y el nuevo PVP calculado.
   - Verificar que `total_base`, `total_vat` y `total_amount` cuadren al céntimo.
3. **Prueba End-to-End en Navegador (Playwright)**:
   - Registrar una factura de compra en `/admin/expenses/create`.
   - Ir al Punto de Venta (`/admin/pos`) y constatar que los productos muestran el nuevo stock y el nuevo precio de venta al público.
4. **Verificación de Regresión**:
   - Ejecutar la suite completa `php artisan test` garantizando 100% de tests aprobados.
