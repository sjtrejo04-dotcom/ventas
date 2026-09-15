# Plan de Implementación: Terminal POS Táctil, Control de Cajas y Facturación SENIAT

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task with specialized subagents (Backend, Frontend, QA). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el módulo completo de punto de venta táctil (POS), control de turnos de caja independientes con arqueo ciego, motor de cálculo fiscal venezolano (con y sin IGTF bimoneda) e impresión de ticket térmico estándar SENIAT (modelos PlanSuárez y Daka).

**Architecture:** Módulo desacoplado en FilamentPHP v4 mediante una `Custom Page` (`PosTerminal`) con Livewire/Alpine.js para la interacción táctil en tiempo real, respaldado por servicios de dominio (`FiscalCalculationService`, `CashShiftService`) y persistencia directa en Supabase PostgreSQL.

**Tech Stack:** Laravel 13, FilamentPHP v4, Livewire 3, Tailwind CSS, Alpine.js, Pest PHP, Supabase PostgreSQL.

**Spec:** [`docs/superpowers/specs/2026-09-06-pos-cajas-facturacion-design.md`](file:///c:/Users/santiago/Documents/development/Ventas-Declaraci-n-/docs/superpowers/specs/2026-09-06-pos-cajas-facturacion-design.md)

---

## Estructura de Roles de Subagentes

Para ejecutar este plan con la máxima especialización y calidad, dividiremos la ejecución entre 3 perfiles de subagentes:

1. **Agente Backend (Laravel / Eloquent / DB Specialist)**:
   - Migraciones y modelos de Cajas (`CashRegister`) y Turnos (`CashShift`).
   - Modificación de esquemas de `sales` y `sale_items`.
   - `FiscalCalculationService` (lógica impositiva estricta SENIAT: Base 16%, Exento, IGTF 3% solo en divisas efectivo, tasa BCV, vuelto dual).
   - `CashShiftService` (apertura, auditoría y cálculo de diferencias en arqueo ciego).
   - Programación de tareas en `routes/console.php`.

2. **Agente Frontend (Filament / Blade / Alpine.js Specialist)**:
   - `PosTerminal` Custom Page (`app/Filament/Admin/Pages/PosTerminal.php`).
   - Vista táctil interactiva (`resources/views/filament/admin/pages/pos-terminal.blade.php`) con atajos `F2`, `F3`, `F4`, `Enter`.
   - Modales de Apertura y Cierre de Turno con Arqueo Ciego.
   - Modal Wizard de Pago Multimoneda con botones rápidos de billetes y cálculo de cambio.
   - Vista de Ticket Térmico imprimible (`58mm / 80mm`) con código QR, adaptado a los formatos de PlanSuárez y Daka.

3. **Agente QA & Testing (Pest Specialist)**:
   - Pruebas unitarias de cálculo fiscal:
     - Escenario Daka: Pago 100% Bolívares (Débito + Cashea) -> `total_igtf == 0`.
     - Escenario PlanSuárez: Pago con Efectivo Divisas -> `total_igtf == 3%` sobre monto en divisa.
     - Ítems mixtos: Separación estricta de Exento `(E)` y Gravable `(G)`.
   - Pruebas de ciclo de caja: Turnos independientes, bloqueo de ventas sin turno abierto, cálculo de sobrante/faltante.
   - Pruebas de integración de extremo a extremo.

---

## Tareas de Implementación

### Tarea 1 (Backend): Migraciones y Modelos de Cajas y Turnos

**Files:**
- Create: `database/migrations/2026_09_06_170000_create_cash_registers_and_shifts_tables.php`
- Create: `app/Models/CashRegister.php`
- Create: `app/Models/CashShift.php`
- Modify: `app/Models/Sale.php`
- Modify: `app/Models/SaleItem.php`
- Test: `tests/Feature/CashShiftModelTest.php`

**Interfaces:**
- Produces: `CashRegister` model, `CashShift` model con relaciones `user()`, `cashRegister()`, `sales()`.
- Modifies: `sales` table añadiendo `cash_shift_id`, `pos_document_number`, `fiscal_serial`. `sale_items` soportando decimales en `quantity`, `serial_number`, `warranty_days`.

- [x] **Paso 1.1: Escribir prueba fallida para modelos de caja y turno**
- [x] **Paso 1.2: Crear migración para `cash_registers`, `cash_shifts` y columnas adicionales en `sales` y `sale_items`**
- [x] **Paso 1.3: Ejecutar migración (`php artisan migrate`) hacia Supabase**
- [x] **Paso 1.4: Implementar modelos `CashRegister` y `CashShift` con casts y relaciones**
- [x] **Paso 1.5: Verificar que la prueba pase y commitear**

---

### Tarea 2 (Backend): Servicio de Cálculo Fiscal Bimoneda y Servicio de Cajas

**Files:**
- Create: `app/Services/FiscalCalculationService.php`
- Create: `app/Services/CashShiftService.php`
- Test: `tests/Unit/FiscalCalculationServiceTest.php`
- Test: `tests/Unit/CashShiftServiceTest.php`

**Interfaces:**
- `FiscalCalculationService::calculate(array $items, array $payments, float $bcvRate): FiscalSummaryDTO`
  - Calcula: `subtotal`, `exempt_amount`, `taxable_base`, `vat_amount`, `igtf_base`, `igtf_amount`, `total_amount_bs`, `total_amount_usd`, `change_due_bs`, `change_due_usd`.
- `CashShiftService::openShift(int $cashRegisterId, int $userId, float $bs, float $usd): CashShift`
- `CashShiftService::closeShift(CashShift $shift, array $declaredAmounts): CashShift` (calcula diferencias del arqueo ciego).

- [x] **Paso 2.1: Escribir pruebas unitarias para cálculo de IVA, IGTF condicional y arqueo ciego**
- [x] **Paso 2.2: Implementar `FiscalCalculationService` con la regla estricta de IGTF (solo divisas efectivo)**
- [x] **Paso 2.3: Implementar `CashShiftService` con gestión de estados y auditoría de diferencias**
- [x] **Paso 2.4: Ejecutar pruebas y verificar éxito completo**
- [x] **Paso 2.5: Commitear**

---

### Tarea 3 (Backend): Automatización de Tasa BCV y Seeder de Cajas

**Files:**
- Modify: `routes/console.php`
- Create: `database/seeders/CashRegisterSeeder.php`
- Test: `tests/Feature/BcvScheduleTest.php`

- [x] **Paso 3.1: Programar `bcv:sync` en `routes/console.php` a las 09:00 y 17:00**
- [x] **Paso 3.2: Crear seeder con la caja principal predeterminada**
- [x] **Paso 3.3: Ejecutar seeder y verificar**
- [x] **Paso 3.4: Commitear**

---

### Tarea 4 (Frontend): Página Custom `PosTerminal` y Grilla Táctil

**Files:**
- Create: `app/Filament/Admin/Pages/PosTerminal.php`
- Create: `resources/views/filament/admin/pages/pos-terminal.blade.php`
- Modify: `resources/views/filament/admin/pages/custom-dashboard.blade.php` (enlace directo al POS)

**Interfaces:**
- Interfaz Livewire reactiva con carrito en memoria (`$cart`), buscador de código de barras (`$searchQuery`), selector de categorías y cliente activo.
- Atajos de teclado Alpine.js: `F2` foco en buscador, `F3` cliente, `F4` cobrar, `Esc` limpiar.

- [x] **Paso 4.1: Crear componente Livewire/Filament `PosTerminal`**
- [x] **Paso 4.2: Diseñar la grilla táctil de productos con precios duales ($ y Bs a tasa BCV)**
- [x] **Paso 4.3: Diseñar el panel derecho de ticket de venta con botones `+`, `-`, eliminar y totalizadores**
- [x] **Paso 4.4: Integrar atajos de teclado con Alpine.js**
- [x] **Paso 4.5: Commitear**

---

### Tarea 5 (Frontend): Modales de Ciclo de Caja (Apertura y Arqueo Ciego)

**Files:**
- Create: `resources/views/filament/admin/pages/pos/shift-modal.blade.php`
- Modify: `app/Filament/Admin/Pages/PosTerminal.php`

- [x] **Paso 5.1: Implementar guardia de bloqueo: si no hay turno abierto, forzar modal de apertura**
- [x] **Paso 5.2: Diseñar modal de arqueo ciego para cierre de turno (conteo de efectivo Bs, $, puntos y pago móvil)**
- [x] **Paso 5.3: Generar reporte de cierre de turno en pantalla con resumen de diferencias**
- [x] **Paso 5.4: Commitear**

---

### Tarea 6 (Frontend): Wizard de Pago Multimoneda e Impresión Térmica

**Files:**
- Create: `resources/views/filament/admin/pages/pos/payment-wizard.blade.php`
- Create: `resources/views/filament/admin/pages/pos/thermal-ticket.blade.php`
- Modify: `app/Filament/Admin/Pages/PosTerminal.php`

- [x] **Paso 6.1: Diseñar modal de cobro guiado con botones rápidos de denominaciones y pagos combinados**
- [x] **Paso 6.2: Implementar cálculo de vuelto en vivo en USD y Bolívares**
- [x] **Paso 6.3: Diseñar plantilla de ticket térmico (58mm/80mm) con el formato exacto SENIAT (PlanSuárez / Daka)**
- [x] **Paso 6.4: Integrar generación de código QR y modal de post-venta con botón de impresión instantánea**
- [x] **Paso 6.5: Commitear**

---

### Tarea 7 (QA): Suite Integral de Pruebas de Facturación y Cajas

**Files:**
- Create: `tests/Feature/PosEndToEndTest.php`
- Create: `tests/Unit/SeniatInvoiceFormatTest.php`

- [x] **Paso 7.1: Test de venta con pago 100% Bolívares (sin IGTF - Caso Daka)**
- [x] **Paso 7.2: Test de venta con pago en Efectivo Divisas (con 3% IGTF - Caso PlanSuárez)**
- [x] **Paso 7.3: Test de cambio de turno de dos cajeros en la misma caja con cierres independientes**
- [x] **Paso 7.4: Test de salida de inventario automática tras la venta**
- [x] **Paso 7.5: Ejecutar suite completa `php artisan test` y verificar 100% aprobada**
- [x] **Paso 7.6: Commitear**
