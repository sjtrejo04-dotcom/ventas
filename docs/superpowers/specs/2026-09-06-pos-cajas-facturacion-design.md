# Especificación de Diseño: Terminal POS Táctil, Control de Cajas y Facturación SENIAT

**Fecha:** 2026-09-06  
**Proyecto:** Ventas Declaración (Laravel 13 + FilamentPHP)  
**Estado:** Aprobado para Planificación  

---

## 1. Resumen Ejecutivo y Objetivos

El sistema evolucionará hacia una solución integral de punto de venta (POS) y cumplimiento fiscal adaptado a la normativa venezolana (SENIAT). El diseño resuelve la separación de turnos de cajeros, agiliza la venta rápida en mostrador con pantalla táctil, calcula dinámicamente impuestos y divisas (con y sin IGTF), y genera comprobantes térmicos fieles a las facturas reales del mercado (modelos PlanSuárez y Daka).

### Objetivos Clave:
1. **Control de Cajas y Turnos Independientes (`CashShift`)**: Permitir que cada trabajador gestione su propia apertura, operaciones y arqueo ciego de cierre, garantizando auditoría de faltantes o sobrantes.
2. **Terminal POS Táctil de Alta Velocidad (`PosTerminal`)**: Página personalizada en Filament con grilla táctil de productos, buscador rápido por código de barras, atajos de teclado (`F2`, `F3`, `F4`, `Enter`) y cliente consumidor final por defecto.
3. **Motor Fiscal Dinámico Bimoneda**:
   - Soporte de pagos en Bolívares y Divisas a tasa oficial BCV.
   - Aplicación de IGTF (3%) únicamente sobre pagos en divisas en efectivo (ej. modelo PlanSuárez).
   - Exclusión de IGTF cuando el pago es 100% en Bolívares vía débito, pago móvil o Cashea (ej. modelo Daka).
   - Soporte de productos exentos `(E)` y gravados `(G)`.
   - Soporte de cantidades con decimales (productos por peso/balanza).
   - Registro de seriales y días de garantía por producto.
4. **Impresión Térmica Fiscal (58mm / 80mm)**: Generación de ticket con código QR, desglose impositivo y pie de imprenta fiscal.
5. **Automatización de Tasa BCV**: Programación automática del comando `bcv:sync` en el Scheduler de Laravel.

---

## 2. Arquitectura del Sistema

```
+-----------------------------------------------------------------------------+
|                          PANEL ADMINISTRATIVO FILAMENT                      |
+-----------------------------------------------------------------------------+
|  [Custom Page] PosTerminal             |  [Filament Resource] SalesResource |
|  - UI Blade + Alpine.js + Tailwind     |  - Consulta administrativa          |
|  - Grilla táctil / Atajos teclado      |  - Filtros por fecha / estado       |
|  - Modal de cobro rápido multimoneda   |  - Auditoría y reimpresión          |
+----------------------------------------+-------------------------------------+
                   |                                        |
                   v                                        v
+-----------------------------------------------------------------------------+
|                         CAPA DE NEGOCIO Y MODELOS                           |
+-----------------------------------------------------------------------------+
|  • CashRegister / CashShift (Control de aperturas, cierres y arqueo)        |
|  • Sale / SaleItem (Ventas, ítems con serial/garantía y balanza)            |
|  • Payment / PaymentMethod (Pagos en Bs, Divisas y Cashea con regla IGTF)   |
|  • ExchangeRate (Tasa BCV del día sincronizada vía Scheduler)               |
|  • InventoryMovement (Kardex automático al procesar la venta)               |
+-----------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------+
|                     BASE DE DATOS EN LA NUBE (SUPABASE)                     |
|  - PostgreSQL 17 / Esquema 'public' conectado vía Pooler SSL                |
+-----------------------------------------------------------------------------+
```

---

## 3. Modelo de Datos y Migraciones

### 3.1 Nueva Tabla: `cash_registers`
Identifica las cajas físicas o terminales del establecimiento.
- `id` (bigint, pk)
- `name` (string: ej. "Caja 01 Mostrador")
- `code` (string: ej. "CAJA-01")
- `is_active` (boolean, default true)
- `created_at`, `updated_at`

### 3.2 Nueva Tabla: `cash_shifts`
Gestiona el turno individual de cada cajero.
- `id` (bigint, pk)
- `cash_register_id` (foreignId -> cash_registers)
- `user_id` (foreignId -> users, cajero responsable)
- `opened_at` (timestamp)
- `closed_at` (timestamp, nullable)
- `status` (string: 'open', 'closed')
- **Apertura:**
  - `opening_cash_bs` (decimal 15,2, default 0)
  - `opening_cash_usd` (decimal 15,2, default 0)
- **Cierre del Sistema (calculado automáticamente):**
  - `system_cash_bs`, `system_cash_usd`
  - `system_pos_bs`, `system_mobile_pay_bs`, `system_cashea_bs`
- **Arqueo Ciego (declarado por el cajero):**
  - `declared_cash_bs`, `declared_cash_usd`
  - `declared_pos_bs`, `declared_mobile_pay_bs`
- **Diferencias registradas:**
  - `difference_cash_bs`, `difference_cash_usd`
- `notes` (text, nullable)
- `created_at`, `updated_at`

### 3.3 Modificaciones en `sales`
- `cash_shift_id` (foreignId -> cash_shifts, nullable en migraciones previas)
- `pos_document_number` (string, nullable: ej. "9-66662")
- `fiscal_serial` (string, nullable: ej. "TIB2000431", "Z7C7028525")

### 3.4 Modificaciones en `sale_items`
- `quantity`: Cambiar o asegurar precisión `decimal(10,3)` para admitir pesos fraccionados (ej. `0.575 kg`).
- `serial_number` (string, nullable: para garantías de hardware/electrónica).
- `warranty_days` (integer, nullable: ej. 90 días).

---

## 4. Componentes y Flujos de Usuario

### 4.1 Ciclo de Caja (`CashShiftManager`)
1. **Guardia de Acceso al POS**: Al ingresar a `/admin/pos`, el componente verifica si el usuario autenticado tiene un turno en estado `'open'`.
   - Si no existe: Muestra el modal **"Apertura de Turno"** exigiendo los montos de fondo de caja inicial (`opening_cash_bs`, `opening_cash_usd`).
2. **Cierre de Turno**:
   - Botón visible en el encabezado del POS: **"Cerrar Turno"**.
   - Abre el modal de **Arqueo Ciego**: el cajero cuenta y coloca los billetes físicos sin ver la cifra del sistema.
   - El sistema almacena la declaración, calcula las diferencias y genera el reporte de cierre (Reporte Z del turno).

### 4.2 Terminal POS Táctil (`PosTerminal`)
* **Ubicación:** `app/Filament/Admin/Pages/PosTerminal.php` con vista `resources/views/filament/admin/pages/pos-terminal.blade.php`.
* **Grilla Izquierda (Catálogo)**:
  - Buscador reactivo superior con autofoco (`F2` para enfocar, compatible con lector de códigos de barras).
  - Filtro táctil por categorías de productos.
  - Tarjetas grandes con botón de adición directa, stock actual y precio dual ($ y Bs).
* **Columna Derecha (Ticket de Venta)**:
  - Selector de cliente express (`F3` para cambiar de Consumidor Final a cliente con RIF).
  - Listado de ítems con botones táctiles `+`, `-` y botón para ingresar serial/garantía si aplica.
  - Totales visibles: Subtotal, Exento, Base Gravada, IVA (16%), IGTF proyectado y Total.
  - Botón principal de cobro: **"Cobrar [F4]"**.

### 4.3 Modal de Pago Multimoneda (Wizard)
* Total a pagar desplegado en Bolívares y en Dólares a la tasa BCV del día.
* **Selección de Métodos de Pago**:
  - Efectivo Bolívares (sin IGTF).
  - Tarjeta de Débito / Punto de Venta (sin IGTF).
  - Pago Móvil (sin IGTF).
  - Cashea / Financiamiento en Bs (sin IGTF).
  - Efectivo Divisas USD (aplica **3% de IGTF** sobre el monto en divisas pagado).
* **Pagos Múltiples y Vuelto**:
  - Permite combinar métodos hasta cubrir el saldo.
  - Si el pago supera el monto, calcula de inmediato el vuelto en USD y en Bs.

### 4.4 Vista de Impresión de Ticket Térmico
* Ruta / Modal: Impresión directa vía `window.print()` con estilos CSS `@media print` para papel térmico continuo (ancho 80mm o 58mm).
* Estructura idéntica al estándar SENIAT:
  - Encabezado legal y RIF del emisor.
  - Indicador `FACTURA`, correlativo fiscal y fecha/hora.
  - Datos del cliente (`V-`, nombre, teléfono).
  - Detalle de productos con marcas `(G)` y `(E)` y detalle de balanza / seriales.
  - Desglose impositivo: Monto Exento, Base Imponible 16%, Alícuota IVA 16%, B.I. IGTF y Monto IGTF (si aplica).
  - Desglose de pagos.
  - Código QR generado dinámicamente y serial fiscal del dispositivo.

---

## 5. Automatización y Tareas Programadas

En `routes/console.php`, programar la sincronización de la tasa oficial del Banco Central de Venezuela:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('bcv:sync')
    ->dailyAt('09:00')
    ->dailyAt('17:00');
```

---

## 6. Plan de Pruebas y Validación

### 6.1 Pruebas Automatizadas (Pest)
1. **`CashShiftTest`**:
   - Verificar que un cajero no puede abrir dos turnos simultáneos en la misma caja.
   - Comprobar el cálculo de diferencias en el arqueo ciego (faltante/sobrante).
2. **`PosSaleCalculationTest`**:
   - Venta 100% en Bolívares (ej. Daka): Verificar que `total_igtf == 0`.
   - Venta con Efectivo Divisas (ej. PlanSuárez): Verificar que el 3% de IGTF se calcula estrictamente sobre el pago en divisas.
   - Venta con productos mixtos: Verificar separación correcta de montos exentos `(E)` y gravados `(G)`.
3. **`InventoryDeductionTest`**:
   - Comprobar que al completar la venta en el POS se generan los `InventoryMovement` de salida correspondientes.

### 6.2 Pruebas Manuales
- Realizar flujo completo en navegador: Apertura de turno -> Cobro de venta mixta en POS -> Verificación visual del ticket térmico -> Cierre y arqueo de caja.
