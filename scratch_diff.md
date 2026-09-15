1897365 Fix financial logic flaws and implement reviewer suggestions
6df6040 feat: add real-time reactivity and calculations to sale form
8b4182c feat: add tax report page for accounting audit
8e18245 feat: implement inventory movements to adjust product stock
5551848 fix: add declare strict types and fix import order in base resources
a246e1f feat: add management resources for providers, payment methods, and exchange rates

 app/Filament/Admin/Pages/TaxReport.php             | 208 +++++++++++
 .../ExchangeRates/ExchangeRateResource.php         |  73 ++++
 .../ExchangeRates/Pages/CreateExchangeRate.php     |  13 +
 .../ExchangeRates/Pages/EditExchangeRate.php       |  21 ++
 .../ExchangeRates/Pages/ListExchangeRates.php      |  21 ++
 .../ExchangeRates/Schemas/ExchangeRateForm.php     |  45 +++
 .../ExchangeRates/Tables/ExchangeRatesTable.php    |  63 ++++
 .../InventoryMovementResource.php                  |  23 ++
 .../Pages/CreateInventoryMovement.php              |   2 +
 .../Pages/EditInventoryMovement.php                |   2 +
 .../Pages/ListInventoryMovements.php               |   2 +
 .../Pages/ViewInventoryMovement.php                |   2 +
 .../Schemas/InventoryMovementForm.php              |  77 ++--
 .../Schemas/InventoryMovementInfolist.php          |  64 ++--
 .../Tables/InventoryMovementsTable.php             |  16 +-
 .../PaymentMethods/Pages/CreatePaymentMethod.php   |  13 +
 .../PaymentMethods/Pages/EditPaymentMethod.php     |  21 ++
 .../PaymentMethods/Pages/ListPaymentMethods.php    |  21 ++
 .../PaymentMethods/PaymentMethodResource.php       |  73 ++++
 .../PaymentMethods/Schemas/PaymentMethodForm.php   |  41 +++
 .../PaymentMethods/Tables/PaymentMethodsTable.php  |  65 ++++
 .../Resources/Providers/Pages/CreateProvider.php   |  13 +
 .../Resources/Providers/Pages/EditProvider.php     |  21 ++
 .../Resources/Providers/Pages/ListProviders.php    |  21 ++
 .../Admin/Resources/Providers/ProviderResource.php |  73 ++++
 .../Resources/Providers/Schemas/ProviderForm.php   |  44 +++
 .../Resources/Providers/Tables/ProvidersTable.php  |  58 +++
 .../Admin/Resources/Sales/Schemas/SaleForm.php     |  79 +++-
 app/Models/InventoryMovement.php                   |  16 +-
 app/Models/User.php                                |   9 +-
 app/Observers/InventoryMovementObserver.php        |  57 +++
 app/Providers/AppServiceProvider.php               |   5 +
 app/Providers/Filament/AdminPanelProvider.php      |   1 +
 .../plans/2026-08-28-accounting-reports.md         | Bin 0 -> 9617 bytes
 .../superpowers/plans/2026-08-28-business-logic.md | 194 ++++++++++
 docs/superpowers/plans/2026-08-28-ventas-fixes.md  | 235 ++++++++++++
 docs/superpowers/plans/plan-theme-modifications.md |  37 ++
 .../filament/admin/pages/tax-report.blade.php      | 400 +++++++++++++++++++++
 tests/Feature/BaseResourcesTest.php                |  80 +++++
 tests/Feature/ExampleTest.php                      |   2 +-
 tests/Feature/InventoryMovementTest.php            | 119 ++++++
 tests/Feature/TaxReportTest.php                    | 150 ++++++++
 42 files changed, 2412 insertions(+), 68 deletions(-)

diff --git a/app/Filament/Admin/Pages/TaxReport.php b/app/Filament/Admin/Pages/TaxReport.php
new file mode 100644
index 0000000..cf25bee
--- /dev/null
+++ b/app/Filament/Admin/Pages/TaxReport.php
@@ -0,0 +1,208 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Pages;
+
+use App\Models\Expense;
+use App\Models\Sale;
+use Carbon\Carbon;
+use Filament\Pages\Page;
+use Filament\Support\Enums\Width;
+use Illuminate\Contracts\Support\Htmlable;
+
+class TaxReport extends Page
+{
+    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
+
+    protected static ?string $navigationLabel = 'Declaración de Impuestos';
+
+    protected static ?int $navigationSort = 20;
+
+    protected string $view = 'filament.admin.pages.tax-report';
+
+    public int $selectedMonth = 1;
+
+    public int $selectedYear = 2026;
+
+    public float $totalSalesBase = 0.0;
+
+    public float $totalSalesVat = 0.0;
+
+    public float $totalSalesAmount = 0.0;
+
+    public float $totalExpenses = 0.0;
+
+    public float $totalExpensesBase = 0.0;
+
+    public float $totalExpensesVat = 0.0;
+
+    public float $netTaxToDeclare = 0.0;
+
+    public float $netBalance = 0.0;
+
+    public int $salesCount = 0;
+
+    public int $expensesCount = 0;
+
+    public function getTitle(): string|Htmlable
+    {
+        return 'Reporte de Declaración de Impuestos';
+    }
+
+    public static function getNavigationGroup(): ?string
+    {
+        return 'Finanzas & Contabilidad';
+    }
+
+    public function getMaxContentWidth(): Width|string|null
+    {
+        return Width::Full;
+    }
+
+    public function mount(): void
+    {
+        $this->selectedMonth = (int) now()->month;
+        $this->selectedYear = (int) now()->year;
+        $this->loadData();
+    }
+
+    public function updatedSelectedMonth(): void
+    {
+        $this->loadData();
+    }
+
+    public function updatedSelectedYear(): void
+    {
+        $this->loadData();
+    }
+
+    public function filter(): void
+    {
+        $this->loadData();
+    }
+
+    public function loadData(): void
+    {
+        $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
+        $endOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth();
+
+        $startDate = $startOfMonth->toDateString();
+        $endDate = $endOfMonth->toDateString();
+
+        $salesQuery = Sale::query()
+            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
+                $query->whereBetween('invoice_date', [$startDate, $endDate])
+                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
+                        $sub->whereNull('invoice_date')
+                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
+                    });
+            });
+
+        $expensesQuery = Expense::query()
+            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
+                $query->whereBetween('expense_date', [$startDate, $endDate])
+                    ->orWhereBetween('invoice_date', [$startDate, $endDate])
+                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
+                        $sub->whereNull('expense_date')
+                            ->whereNull('invoice_date')
+                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
+                    });
+            });
+
+        $this->salesCount = (int) $salesQuery->count();
+        $this->totalSalesBase = (float) round((float) $salesQuery->sum('total_base'), 2);
+        $this->totalSalesVat = (float) round((float) $salesQuery->sum('total_vat'), 2);
+        $this->totalSalesAmount = (float) round((float) $salesQuery->sum('total_amount'), 2);
+
+        $this->expensesCount = (int) $expensesQuery->count();
+        $this->totalExpenses = (float) round((float) $expensesQuery->sum('total_amount'), 2);
+        $this->totalExpensesBase = (float) round((float) $expensesQuery->sum('total_base'), 2);
+        $this->totalExpensesVat = (float) round((float) $expensesQuery->sum('total_vat'), 2);
+
+        // El IVA Neto a Declarar es el Débito Fiscal menos el Crédito Fiscal
+        $this->netTaxToDeclare = $this->totalSalesVat - $this->totalExpensesVat;
+
+        // Balance Operativo: Base Imponible de Ventas - Total Gastos
+        $this->netBalance = (float) round($this->totalSalesBase - $this->totalExpenses, 2);
+    }
+
+    /**
+     * @return array<int, string>
+     */
+    public function getMonths(): array
+    {
+        return [
+            1 => 'Enero',
+            2 => 'Febrero',
+            3 => 'Marzo',
+            4 => 'Abril',
+            5 => 'Mayo',
+            6 => 'Junio',
+            7 => 'Julio',
+            8 => 'Agosto',
+            9 => 'Septiembre',
+            10 => 'Octubre',
+            11 => 'Noviembre',
+            12 => 'Diciembre',
+        ];
+    }
+
+    /**
+     * @return array<int, int>
+     */
+    public function getYears(): array
+    {
+        $currentYear = (int) now()->year;
+        $years = [];
+        for ($y = $currentYear - 4; $y <= $currentYear + 1; $y++) {
+            $years[$y] = $y;
+        }
+
+        return $years;
+    }
+
+    protected function getViewData(): array
+    {
+        $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
+        $endOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth();
+        $startDate = $startOfMonth->toDateString();
+        $endDate = $endOfMonth->toDateString();
+
+        $recentSales = Sale::with(['customer', 'user'])
+            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
+                $query->whereBetween('invoice_date', [$startDate, $endDate])
+                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
+                        $sub->whereNull('invoice_date')
+                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
+                    });
+            })
+            ->latest('invoice_date')
+            ->latest('id')
+            ->take(15)
+            ->get();
+
+        $recentExpenses = Expense::with(['provider', 'user'])
+            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
+                $query->whereBetween('expense_date', [$startDate, $endDate])
+                    ->orWhereBetween('invoice_date', [$startDate, $endDate])
+                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
+                        $sub->whereNull('expense_date')
+                            ->whereNull('invoice_date')
+                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
+                    });
+            })
+            ->latest('expense_date')
+            ->latest('id')
+            ->take(15)
+            ->get();
+
+        return [
+            'months' => $this->getMonths(),
+            'years' => $this->getYears(),
+            'periodName' => ($this->getMonths()[$this->selectedMonth] ?? '').' '.$this->selectedYear,
+            'recentSales' => $recentSales,
+            'recentExpenses' => $recentExpenses,
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/ExchangeRates/ExchangeRateResource.php b/app/Filament/Admin/Resources/ExchangeRates/ExchangeRateResource.php
new file mode 100644
index 0000000..6421752
--- /dev/null
+++ b/app/Filament/Admin/Resources/ExchangeRates/ExchangeRateResource.php
@@ -0,0 +1,73 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\ExchangeRates;
+
+use App\Filament\Admin\Resources\ExchangeRates\Pages\CreateExchangeRate;
+use App\Filament\Admin\Resources\ExchangeRates\Pages\EditExchangeRate;
+use App\Filament\Admin\Resources\ExchangeRates\Pages\ListExchangeRates;
+use App\Filament\Admin\Resources\ExchangeRates\Schemas\ExchangeRateForm;
+use App\Filament\Admin\Resources\ExchangeRates\Tables\ExchangeRatesTable;
+use App\Models\ExchangeRate;
+use BackedEnum;
+use Filament\Resources\Resource;
+use Filament\Schemas\Schema;
+use Filament\Support\Icons\Heroicon;
+use Filament\Tables\Table;
+use UnitEnum;
+
+class ExchangeRateResource extends Resource
+{
+    protected static ?string $model = ExchangeRate::class;
+
+    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
+
+    protected static ?string $recordTitleAttribute = 'currency';
+
+    public static function getModelLabel(): string
+    {
+        return 'Tasa de Cambio';
+    }
+
+    public static function getPluralModelLabel(): string
+    {
+        return 'Tasas de Cambio';
+    }
+
+    public static function getNavigationLabel(): string
+    {
+        return 'Tasas de Cambio';
+    }
+
+    public static function getNavigationGroup(): string|UnitEnum|null
+    {
+        return 'Finanzas & Contabilidad';
+    }
+
+    public static function form(Schema $schema): Schema
+    {
+        return ExchangeRateForm::configure($schema);
+    }
+
+    public static function table(Table $table): Table
+    {
+        return ExchangeRatesTable::configure($table);
+    }
+
+    public static function getRelations(): array
+    {
+        return [
+            //
+        ];
+    }
+
+    public static function getPages(): array
+    {
+        return [
+            'index' => ListExchangeRates::route('/'),
+            'create' => CreateExchangeRate::route('/create'),
+            'edit' => EditExchangeRate::route('/{record}/edit'),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/ExchangeRates/Pages/CreateExchangeRate.php b/app/Filament/Admin/Resources/ExchangeRates/Pages/CreateExchangeRate.php
new file mode 100644
index 0000000..364806c
--- /dev/null
+++ b/app/Filament/Admin/Resources/ExchangeRates/Pages/CreateExchangeRate.php
@@ -0,0 +1,13 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\ExchangeRates\Pages;
+
+use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;
+use Filament\Resources\Pages\CreateRecord;
+
+class CreateExchangeRate extends CreateRecord
+{
+    protected static string $resource = ExchangeRateResource::class;
+}
diff --git a/app/Filament/Admin/Resources/ExchangeRates/Pages/EditExchangeRate.php b/app/Filament/Admin/Resources/ExchangeRates/Pages/EditExchangeRate.php
new file mode 100644
index 0000000..67aaa71
--- /dev/null
+++ b/app/Filament/Admin/Resources/ExchangeRates/Pages/EditExchangeRate.php
@@ -0,0 +1,21 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\ExchangeRates\Pages;
+
+use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;
+use Filament\Actions\DeleteAction;
+use Filament\Resources\Pages\EditRecord;
+
+class EditExchangeRate extends EditRecord
+{
+    protected static string $resource = ExchangeRateResource::class;
+
+    protected function getHeaderActions(): array
+    {
+        return [
+            DeleteAction::make(),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/ExchangeRates/Pages/ListExchangeRates.php b/app/Filament/Admin/Resources/ExchangeRates/Pages/ListExchangeRates.php
new file mode 100644
index 0000000..9577d6d
--- /dev/null
+++ b/app/Filament/Admin/Resources/ExchangeRates/Pages/ListExchangeRates.php
@@ -0,0 +1,21 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\ExchangeRates\Pages;
+
+use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;
+use Filament\Actions\CreateAction;
+use Filament\Resources\Pages\ListRecords;
+
+class ListExchangeRates extends ListRecords
+{
+    protected static string $resource = ExchangeRateResource::class;
+
+    protected function getHeaderActions(): array
+    {
+        return [
+            CreateAction::make(),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/ExchangeRates/Schemas/ExchangeRateForm.php b/app/Filament/Admin/Resources/ExchangeRates/Schemas/ExchangeRateForm.php
new file mode 100644
index 0000000..40a2005
--- /dev/null
+++ b/app/Filament/Admin/Resources/ExchangeRates/Schemas/ExchangeRateForm.php
@@ -0,0 +1,45 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\ExchangeRates\Schemas;
+
+use Filament\Forms\Components\DateTimePicker;
+use Filament\Forms\Components\Select;
+use Filament\Forms\Components\TextInput;
+use Filament\Schemas\Components\Section;
+use Filament\Schemas\Schema;
+
+class ExchangeRateForm
+{
+    public static function configure(Schema $schema): Schema
+    {
+        return $schema
+            ->components([
+                Section::make('Información de la Tasa de Cambio')
+                    ->schema([
+                        Select::make('currency')
+                            ->label('Moneda')
+                            ->options([
+                                'USD' => 'Dólar Estadounidense (USD)',
+                                'EUR' => 'Euro (EUR)',
+                                'COP' => 'Peso Colombiano (COP)',
+                            ])
+                            ->default('USD')
+                            ->required(),
+                        TextInput::make('rate')
+                            ->label('Tasa Oficial (Bs por unidad)')
+                            ->placeholder('Ej. 36.500000')
+                            ->required()
+                            ->numeric()
+                            ->minValue(0.000001)
+                            ->step('0.000001')
+                            ->helperText('Monto equivalente en Bolívares (VES).'),
+                        DateTimePicker::make('date_published')
+                            ->label('Fecha y Hora de Publicación')
+                            ->default(now())
+                            ->required(),
+                    ])->columns(3),
+            ]);
+    }
+}
diff --git a/app/Filament/Admin/Resources/ExchangeRates/Tables/ExchangeRatesTable.php b/app/Filament/Admin/Resources/ExchangeRates/Tables/ExchangeRatesTable.php
new file mode 100644
index 0000000..9c101ef
--- /dev/null
+++ b/app/Filament/Admin/Resources/ExchangeRates/Tables/ExchangeRatesTable.php
@@ -0,0 +1,63 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\ExchangeRates\Tables;
+
+use Filament\Actions\BulkActionGroup;
+use Filament\Actions\DeleteBulkAction;
+use Filament\Actions\EditAction;
+use Filament\Tables\Columns\TextColumn;
+use Filament\Tables\Filters\SelectFilter;
+use Filament\Tables\Table;
+
+class ExchangeRatesTable
+{
+    public static function configure(Table $table): Table
+    {
+        return $table
+            ->columns([
+                TextColumn::make('currency')
+                    ->label('Moneda')
+                    ->badge()
+                    ->searchable()
+                    ->sortable(),
+                TextColumn::make('rate')
+                    ->label('Tasa Oficial (VES)')
+                    ->numeric(decimalPlaces: 4)
+                    ->sortable(),
+                TextColumn::make('date_published')
+                    ->label('Fecha Publicada')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable(),
+                TextColumn::make('created_at')
+                    ->label('Registrado el')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+                TextColumn::make('updated_at')
+                    ->label('Actualizado el')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+            ])
+            ->defaultSort('date_published', 'desc')
+            ->filters([
+                SelectFilter::make('currency')
+                    ->label('Moneda')
+                    ->options([
+                        'USD' => 'USD',
+                        'EUR' => 'EUR',
+                        'COP' => 'COP',
+                    ]),
+            ])
+            ->recordActions([
+                EditAction::make(),
+            ])
+            ->toolbarActions([
+                BulkActionGroup::make([
+                    DeleteBulkAction::make(),
+                ]),
+            ]);
+    }
+}
diff --git a/app/Filament/Admin/Resources/InventoryMovements/InventoryMovementResource.php b/app/Filament/Admin/Resources/InventoryMovements/InventoryMovementResource.php
index fc57203..6a8b0c6 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/InventoryMovementResource.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/InventoryMovementResource.php
@@ -1,36 +1,59 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements;
 
 use App\Filament\Admin\Resources\InventoryMovements\Pages\CreateInventoryMovement;
 use App\Filament\Admin\Resources\InventoryMovements\Pages\EditInventoryMovement;
 use App\Filament\Admin\Resources\InventoryMovements\Pages\ListInventoryMovements;
 use App\Filament\Admin\Resources\InventoryMovements\Pages\ViewInventoryMovement;
 use App\Filament\Admin\Resources\InventoryMovements\Schemas\InventoryMovementForm;
 use App\Filament\Admin\Resources\InventoryMovements\Schemas\InventoryMovementInfolist;
 use App\Filament\Admin\Resources\InventoryMovements\Tables\InventoryMovementsTable;
 use App\Models\InventoryMovement;
 use BackedEnum;
 use Filament\Resources\Resource;
 use Filament\Schemas\Schema;
 use Filament\Support\Icons\Heroicon;
 use Filament\Tables\Table;
+use UnitEnum;
 
 class InventoryMovementResource extends Resource
 {
     protected static ?string $model = InventoryMovement::class;
 
     protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
 
     protected static ?string $recordTitleAttribute = 'concept';
 
+    public static function getModelLabel(): string
+    {
+        return 'Movimiento de Inventario';
+    }
+
+    public static function getPluralModelLabel(): string
+    {
+        return 'Movimientos de Inventario';
+    }
+
+    public static function getNavigationLabel(): string
+    {
+        return 'Movimientos de Inventario';
+    }
+
+    public static function getNavigationGroup(): string|UnitEnum|null
+    {
+        return 'Inventario';
+    }
+
     public static function form(Schema $schema): Schema
     {
         return InventoryMovementForm::configure($schema);
     }
 
     public static function infolist(Schema $schema): Schema
     {
         return InventoryMovementInfolist::configure($schema);
     }
 
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Pages/CreateInventoryMovement.php b/app/Filament/Admin/Resources/InventoryMovements/Pages/CreateInventoryMovement.php
index 70b7590..f81966e 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Pages/CreateInventoryMovement.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Pages/CreateInventoryMovement.php
@@ -1,11 +1,13 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Pages;
 
 use App\Filament\Admin\Resources\InventoryMovements\InventoryMovementResource;
 use Filament\Resources\Pages\CreateRecord;
 
 class CreateInventoryMovement extends CreateRecord
 {
     protected static string $resource = InventoryMovementResource::class;
 }
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Pages/EditInventoryMovement.php b/app/Filament/Admin/Resources/InventoryMovements/Pages/EditInventoryMovement.php
index 6d6de46..a800557 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Pages/EditInventoryMovement.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Pages/EditInventoryMovement.php
@@ -1,12 +1,14 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Pages;
 
 use App\Filament\Admin\Resources\InventoryMovements\InventoryMovementResource;
 use Filament\Actions\DeleteAction;
 use Filament\Actions\ViewAction;
 use Filament\Resources\Pages\EditRecord;
 
 class EditInventoryMovement extends EditRecord
 {
     protected static string $resource = InventoryMovementResource::class;
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Pages/ListInventoryMovements.php b/app/Filament/Admin/Resources/InventoryMovements/Pages/ListInventoryMovements.php
index f9c34e6..fe50750 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Pages/ListInventoryMovements.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Pages/ListInventoryMovements.php
@@ -1,12 +1,14 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Pages;
 
 use App\Filament\Admin\Resources\InventoryMovements\InventoryMovementResource;
 use Filament\Actions\CreateAction;
 use Filament\Resources\Pages\ListRecords;
 
 class ListInventoryMovements extends ListRecords
 {
     protected static string $resource = InventoryMovementResource::class;
 
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Pages/ViewInventoryMovement.php b/app/Filament/Admin/Resources/InventoryMovements/Pages/ViewInventoryMovement.php
index 9b5719b..6d0a1b6 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Pages/ViewInventoryMovement.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Pages/ViewInventoryMovement.php
@@ -1,12 +1,14 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Pages;
 
 use App\Filament\Admin\Resources\InventoryMovements\InventoryMovementResource;
 use Filament\Actions\EditAction;
 use Filament\Resources\Pages\ViewRecord;
 
 class ViewInventoryMovement extends ViewRecord
 {
     protected static string $resource = InventoryMovementResource::class;
 
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementForm.php b/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementForm.php
index e1111af..b672a37 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementForm.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementForm.php
@@ -1,38 +1,71 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Schemas;
 
+use App\Models\Product;
+use Filament\Forms\Components\Select;
 use Filament\Forms\Components\TextInput;
+use Filament\Schemas\Components\Section;
 use Filament\Schemas\Schema;
 
 class InventoryMovementForm
 {
     public static function configure(Schema $schema): Schema
     {
         return $schema
             ->components([
-                TextInput::make('product_id')
-                    ->required()
-                    ->numeric(),
-                TextInput::make('user_id')
-                    ->numeric(),
-                TextInput::make('type')
-                    ->required(),
-                TextInput::make('concept')
-                    ->required(),
-                TextInput::make('quantity')
-                    ->required()
-                    ->numeric(),
-                TextInput::make('unit_cost')
-                    ->required()
-                    ->numeric()
-                    ->prefix('$'),
-                TextInput::make('stock_after_movement')
-                    ->required()
-                    ->numeric(),
-                TextInput::make('reference_type'),
-                TextInput::make('reference_id')
-                    ->numeric(),
+                Section::make('Información del Movimiento')
+                    ->schema([
+                        Select::make('product_id')
+                            ->label('Producto')
+                            ->relationship('product', 'name')
+                            ->searchable()
+                            ->preload()
+                            ->required()
+                            ->reactive()
+                            ->afterStateUpdated(function ($state, callable $set) {
+                                if ($state) {
+                                    $product = Product::find($state);
+                                    if ($product) {
+                                        $set('unit_cost', $product->cost ?? 0);
+                                    }
+                                }
+                            }),
+
+                        Select::make('type')
+                            ->label('Tipo de Movimiento')
+                            ->options([
+                                'in' => 'Entrada (Compra / Ingreso)',
+                                'out' => 'Salida (Merma / Retiro)',
+                                'adjustment' => 'Ajuste de Inventario',
+                            ])
+                            ->default('in')
+                            ->required(),
+
+                        TextInput::make('quantity')
+                            ->label('Cantidad')
+                            ->numeric()
+                            ->minValue(0.01)
+                            ->default(1)
+                            ->required(),
+
+                        TextInput::make('unit_cost')
+                            ->label('Costo Unitario')
+                            ->numeric()
+                            ->prefix('$')
+                            ->default(0)
+                            ->required(),
+
+                        TextInput::make('concept')
+                            ->label('Concepto / Descripción')
+                            ->placeholder('Ej: Compra de inventario, Mercancía dañada, Conteo físico')
+                            ->default('Movimiento manual de inventario')
+                            ->required()
+                            ->maxLength(255)
+                            ->columnSpanFull(),
+                    ])->columns(2),
             ]);
     }
 }
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementInfolist.php b/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementInfolist.php
index c1770c5..bf3c13a 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementInfolist.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Schemas/InventoryMovementInfolist.php
@@ -1,40 +1,56 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Schemas;
 
 use Filament\Infolists\Components\TextEntry;
+use Filament\Schemas\Components\Section;
 use Filament\Schemas\Schema;
 
 class InventoryMovementInfolist
 {
     public static function configure(Schema $schema): Schema
     {
         return $schema
             ->components([
-                TextEntry::make('product_id')
-                    ->numeric(),
-                TextEntry::make('user_id')
-                    ->numeric()
-                    ->placeholder('-'),
-                TextEntry::make('type'),
-                TextEntry::make('concept'),
-                TextEntry::make('quantity')
-                    ->numeric(),
-                TextEntry::make('unit_cost')
-                    ->money(),
-                TextEntry::make('stock_after_movement')
-                    ->numeric(),
-                TextEntry::make('reference_type')
-                    ->placeholder('-'),
-                TextEntry::make('reference_id')
-                    ->numeric()
-                    ->placeholder('-'),
-                TextEntry::make('created_at')
-                    ->dateTime()
-                    ->placeholder('-'),
-                TextEntry::make('updated_at')
-                    ->dateTime()
-                    ->placeholder('-'),
+                Section::make('Información del Movimiento')
+                    ->schema([
+                        TextEntry::make('created_at')
+                            ->label('Fecha del Movimiento')
+                            ->dateTime('d/m/Y H:i'),
+                        TextEntry::make('product.name')
+                            ->label('Producto'),
+                        TextEntry::make('type')
+                            ->label('Tipo')
+                            ->badge()
+                            ->color(fn (string $state): string => match ($state) {
+                                'in' => 'success',
+                                'out' => 'danger',
+                                'adjustment' => 'warning',
+                                default => 'gray',
+                            })
+                            ->formatStateUsing(fn (string $state): string => match ($state) {
+                                'in' => 'Entrada',
+                                'out' => 'Salida',
+                                'adjustment' => 'Ajuste',
+                                default => $state,
+                            }),
+                        TextEntry::make('concept')
+                            ->label('Concepto / Descripción'),
+                        TextEntry::make('quantity')
+                            ->label('Cantidad')
+                            ->numeric(),
+                        TextEntry::make('stock_after_movement')
+                            ->label('Stock Resultante')
+                            ->numeric(),
+                        TextEntry::make('unit_cost')
+                            ->label('Costo Unitario')
+                            ->money('USD'),
+                        TextEntry::make('user.name')
+                            ->label('Registrado por')
+                            ->placeholder('Sistema / Automático'),
+                    ])->columns(2),
             ]);
     }
 }
diff --git a/app/Filament/Admin/Resources/InventoryMovements/Tables/InventoryMovementsTable.php b/app/Filament/Admin/Resources/InventoryMovements/Tables/InventoryMovementsTable.php
index ecaba9d..1660083 100644
--- a/app/Filament/Admin/Resources/InventoryMovements/Tables/InventoryMovementsTable.php
+++ b/app/Filament/Admin/Resources/InventoryMovements/Tables/InventoryMovementsTable.php
@@ -1,17 +1,16 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\InventoryMovements\Tables;
 
-use Filament\Actions\BulkActionGroup;
-use Filament\Actions\DeleteBulkAction;
-use Filament\Actions\EditAction;
 use Filament\Actions\ViewAction;
 use Filament\Tables\Columns\TextColumn;
 use Filament\Tables\Table;
 
 class InventoryMovementsTable
 {
     public static function configure(Table $table): Table
     {
         return $table
             ->columns([
@@ -33,44 +32,47 @@ public static function configure(Table $table): Table
                         default => 'gray',
                     })
                     ->formatStateUsing(fn (string $state): string => match ($state) {
                         'in' => 'Entrada',
                         'out' => 'Salida',
                         'adjustment' => 'Ajuste',
                         default => $state,
                     })
                     ->searchable(),
                 TextColumn::make('concept')
-                    ->label('Concepto')
+                    ->label('Concepto / Descripción')
                     ->searchable(),
                 TextColumn::make('quantity')
                     ->label('Cantidad')
                     ->numeric()
                     ->sortable(),
                 TextColumn::make('stock_after_movement')
                     ->label('Saldo Restante')
                     ->numeric()
                     ->sortable(),
                 TextColumn::make('unit_cost')
                     ->label('Costo Unitario')
-                    ->money('USD') // Change currency as needed
+                    ->money('USD')
                     ->sortable(),
                 TextColumn::make('user.name')
                     ->label('Usuario (Trabajador)')
                     ->searchable()
                     ->sortable(),
                 TextColumn::make('reference_type')
                     ->label('Documento')
                     ->formatStateUsing(function ($state, $record) {
-                        if (!$state) return 'Manual';
+                        if (! $state) {
+                            return 'Manual';
+                        }
                         $class = class_basename($state);
-                        return $class . ' #' . $record->reference_id;
+
+                        return $class.' #'.$record->reference_id;
                     }),
             ])
             ->defaultSort('created_at', 'desc')
             ->filters([
                 //
             ])
             ->recordActions([
                 ViewAction::make(),
             ])
             ->bulkActions([
diff --git a/app/Filament/Admin/Resources/PaymentMethods/Pages/CreatePaymentMethod.php b/app/Filament/Admin/Resources/PaymentMethods/Pages/CreatePaymentMethod.php
new file mode 100644
index 0000000..8f681d5
--- /dev/null
+++ b/app/Filament/Admin/Resources/PaymentMethods/Pages/CreatePaymentMethod.php
@@ -0,0 +1,13 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\PaymentMethods\Pages;
+
+use App\Filament\Admin\Resources\PaymentMethods\PaymentMethodResource;
+use Filament\Resources\Pages\CreateRecord;
+
+class CreatePaymentMethod extends CreateRecord
+{
+    protected static string $resource = PaymentMethodResource::class;
+}
diff --git a/app/Filament/Admin/Resources/PaymentMethods/Pages/EditPaymentMethod.php b/app/Filament/Admin/Resources/PaymentMethods/Pages/EditPaymentMethod.php
new file mode 100644
index 0000000..bbce2d1
--- /dev/null
+++ b/app/Filament/Admin/Resources/PaymentMethods/Pages/EditPaymentMethod.php
@@ -0,0 +1,21 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\PaymentMethods\Pages;
+
+use App\Filament\Admin\Resources\PaymentMethods\PaymentMethodResource;
+use Filament\Actions\DeleteAction;
+use Filament\Resources\Pages\EditRecord;
+
+class EditPaymentMethod extends EditRecord
+{
+    protected static string $resource = PaymentMethodResource::class;
+
+    protected function getHeaderActions(): array
+    {
+        return [
+            DeleteAction::make(),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/PaymentMethods/Pages/ListPaymentMethods.php b/app/Filament/Admin/Resources/PaymentMethods/Pages/ListPaymentMethods.php
new file mode 100644
index 0000000..ff0803f
--- /dev/null
+++ b/app/Filament/Admin/Resources/PaymentMethods/Pages/ListPaymentMethods.php
@@ -0,0 +1,21 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\PaymentMethods\Pages;
+
+use App\Filament\Admin\Resources\PaymentMethods\PaymentMethodResource;
+use Filament\Actions\CreateAction;
+use Filament\Resources\Pages\ListRecords;
+
+class ListPaymentMethods extends ListRecords
+{
+    protected static string $resource = PaymentMethodResource::class;
+
+    protected function getHeaderActions(): array
+    {
+        return [
+            CreateAction::make(),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/PaymentMethods/PaymentMethodResource.php b/app/Filament/Admin/Resources/PaymentMethods/PaymentMethodResource.php
new file mode 100644
index 0000000..8b39310
--- /dev/null
+++ b/app/Filament/Admin/Resources/PaymentMethods/PaymentMethodResource.php
@@ -0,0 +1,73 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\PaymentMethods;
+
+use App\Filament\Admin\Resources\PaymentMethods\Pages\CreatePaymentMethod;
+use App\Filament\Admin\Resources\PaymentMethods\Pages\EditPaymentMethod;
+use App\Filament\Admin\Resources\PaymentMethods\Pages\ListPaymentMethods;
+use App\Filament\Admin\Resources\PaymentMethods\Schemas\PaymentMethodForm;
+use App\Filament\Admin\Resources\PaymentMethods\Tables\PaymentMethodsTable;
+use App\Models\PaymentMethod;
+use BackedEnum;
+use Filament\Resources\Resource;
+use Filament\Schemas\Schema;
+use Filament\Support\Icons\Heroicon;
+use Filament\Tables\Table;
+use UnitEnum;
+
+class PaymentMethodResource extends Resource
+{
+    protected static ?string $model = PaymentMethod::class;
+
+    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
+
+    protected static ?string $recordTitleAttribute = 'name';
+
+    public static function getModelLabel(): string
+    {
+        return 'Método de Pago';
+    }
+
+    public static function getPluralModelLabel(): string
+    {
+        return 'Métodos de Pago';
+    }
+
+    public static function getNavigationLabel(): string
+    {
+        return 'Métodos de Pago';
+    }
+
+    public static function getNavigationGroup(): string|UnitEnum|null
+    {
+        return 'Finanzas & Contabilidad';
+    }
+
+    public static function form(Schema $schema): Schema
+    {
+        return PaymentMethodForm::configure($schema);
+    }
+
+    public static function table(Table $table): Table
+    {
+        return PaymentMethodsTable::configure($table);
+    }
+
+    public static function getRelations(): array
+    {
+        return [
+            //
+        ];
+    }
+
+    public static function getPages(): array
+    {
+        return [
+            'index' => ListPaymentMethods::route('/'),
+            'create' => CreatePaymentMethod::route('/create'),
+            'edit' => EditPaymentMethod::route('/{record}/edit'),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/PaymentMethods/Schemas/PaymentMethodForm.php b/app/Filament/Admin/Resources/PaymentMethods/Schemas/PaymentMethodForm.php
new file mode 100644
index 0000000..89bd459
--- /dev/null
+++ b/app/Filament/Admin/Resources/PaymentMethods/Schemas/PaymentMethodForm.php
@@ -0,0 +1,41 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\PaymentMethods\Schemas;
+
+use Filament\Forms\Components\TextInput;
+use Filament\Forms\Components\Toggle;
+use Filament\Schemas\Components\Section;
+use Filament\Schemas\Schema;
+
+class PaymentMethodForm
+{
+    public static function configure(Schema $schema): Schema
+    {
+        return $schema
+            ->components([
+                Section::make('Información del Método de Pago')
+                    ->schema([
+                        TextInput::make('name')
+                            ->label('Nombre del Método')
+                            ->placeholder('Ej. Transferencia Bancaria, Pago Móvil, Efectivo USD')
+                            ->required()
+                            ->maxLength(255)
+                            ->columnSpanFull(),
+                        Toggle::make('requires_reference')
+                            ->label('Requiere Referencia')
+                            ->helperText('Activar si este método exige comprobante o número de referencia.')
+                            ->default(false),
+                        Toggle::make('applies_igtf')
+                            ->label('Aplica IGTF (3%)')
+                            ->helperText('Activar si los pagos con este método están sujetos al IGTF.')
+                            ->default(false),
+                        Toggle::make('is_active')
+                            ->label('Activo')
+                            ->helperText('Disponible para nuevas transacciones.')
+                            ->default(true),
+                    ])->columns(3),
+            ]);
+    }
+}
diff --git a/app/Filament/Admin/Resources/PaymentMethods/Tables/PaymentMethodsTable.php b/app/Filament/Admin/Resources/PaymentMethods/Tables/PaymentMethodsTable.php
new file mode 100644
index 0000000..196e519
--- /dev/null
+++ b/app/Filament/Admin/Resources/PaymentMethods/Tables/PaymentMethodsTable.php
@@ -0,0 +1,65 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\PaymentMethods\Tables;
+
+use Filament\Actions\BulkActionGroup;
+use Filament\Actions\DeleteBulkAction;
+use Filament\Actions\EditAction;
+use Filament\Tables\Columns\IconColumn;
+use Filament\Tables\Columns\TextColumn;
+use Filament\Tables\Filters\TernaryFilter;
+use Filament\Tables\Table;
+
+class PaymentMethodsTable
+{
+    public static function configure(Table $table): Table
+    {
+        return $table
+            ->columns([
+                TextColumn::make('name')
+                    ->label('Nombre')
+                    ->searchable()
+                    ->sortable(),
+                IconColumn::make('requires_reference')
+                    ->label('Req. Referencia')
+                    ->boolean()
+                    ->sortable(),
+                IconColumn::make('applies_igtf')
+                    ->label('Aplica IGTF')
+                    ->boolean()
+                    ->sortable(),
+                IconColumn::make('is_active')
+                    ->label('Activo')
+                    ->boolean()
+                    ->sortable(),
+                TextColumn::make('created_at')
+                    ->label('Creado el')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+                TextColumn::make('updated_at')
+                    ->label('Actualizado el')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+            ])
+            ->filters([
+                TernaryFilter::make('is_active')
+                    ->label('Estado Activo'),
+                TernaryFilter::make('applies_igtf')
+                    ->label('Aplica IGTF'),
+                TernaryFilter::make('requires_reference')
+                    ->label('Requiere Referencia'),
+            ])
+            ->recordActions([
+                EditAction::make(),
+            ])
+            ->toolbarActions([
+                BulkActionGroup::make([
+                    DeleteBulkAction::make(),
+                ]),
+            ]);
+    }
+}
diff --git a/app/Filament/Admin/Resources/Providers/Pages/CreateProvider.php b/app/Filament/Admin/Resources/Providers/Pages/CreateProvider.php
new file mode 100644
index 0000000..6094075
--- /dev/null
+++ b/app/Filament/Admin/Resources/Providers/Pages/CreateProvider.php
@@ -0,0 +1,13 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\Providers\Pages;
+
+use App\Filament\Admin\Resources\Providers\ProviderResource;
+use Filament\Resources\Pages\CreateRecord;
+
+class CreateProvider extends CreateRecord
+{
+    protected static string $resource = ProviderResource::class;
+}
diff --git a/app/Filament/Admin/Resources/Providers/Pages/EditProvider.php b/app/Filament/Admin/Resources/Providers/Pages/EditProvider.php
new file mode 100644
index 0000000..57ce4d8
--- /dev/null
+++ b/app/Filament/Admin/Resources/Providers/Pages/EditProvider.php
@@ -0,0 +1,21 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\Providers\Pages;
+
+use App\Filament\Admin\Resources\Providers\ProviderResource;
+use Filament\Actions\DeleteAction;
+use Filament\Resources\Pages\EditRecord;
+
+class EditProvider extends EditRecord
+{
+    protected static string $resource = ProviderResource::class;
+
+    protected function getHeaderActions(): array
+    {
+        return [
+            DeleteAction::make(),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/Providers/Pages/ListProviders.php b/app/Filament/Admin/Resources/Providers/Pages/ListProviders.php
new file mode 100644
index 0000000..fad4f30
--- /dev/null
+++ b/app/Filament/Admin/Resources/Providers/Pages/ListProviders.php
@@ -0,0 +1,21 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\Providers\Pages;
+
+use App\Filament\Admin\Resources\Providers\ProviderResource;
+use Filament\Actions\CreateAction;
+use Filament\Resources\Pages\ListRecords;
+
+class ListProviders extends ListRecords
+{
+    protected static string $resource = ProviderResource::class;
+
+    protected function getHeaderActions(): array
+    {
+        return [
+            CreateAction::make(),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/Providers/ProviderResource.php b/app/Filament/Admin/Resources/Providers/ProviderResource.php
new file mode 100644
index 0000000..f8d24df
--- /dev/null
+++ b/app/Filament/Admin/Resources/Providers/ProviderResource.php
@@ -0,0 +1,73 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\Providers;
+
+use App\Filament\Admin\Resources\Providers\Pages\CreateProvider;
+use App\Filament\Admin\Resources\Providers\Pages\EditProvider;
+use App\Filament\Admin\Resources\Providers\Pages\ListProviders;
+use App\Filament\Admin\Resources\Providers\Schemas\ProviderForm;
+use App\Filament\Admin\Resources\Providers\Tables\ProvidersTable;
+use App\Models\Provider;
+use BackedEnum;
+use Filament\Resources\Resource;
+use Filament\Schemas\Schema;
+use Filament\Support\Icons\Heroicon;
+use Filament\Tables\Table;
+use UnitEnum;
+
+class ProviderResource extends Resource
+{
+    protected static ?string $model = Provider::class;
+
+    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
+
+    protected static ?string $recordTitleAttribute = 'name';
+
+    public static function getModelLabel(): string
+    {
+        return 'Proveedor';
+    }
+
+    public static function getPluralModelLabel(): string
+    {
+        return 'Proveedores';
+    }
+
+    public static function getNavigationLabel(): string
+    {
+        return 'Proveedores';
+    }
+
+    public static function getNavigationGroup(): string|UnitEnum|null
+    {
+        return 'Finanzas & Contabilidad';
+    }
+
+    public static function form(Schema $schema): Schema
+    {
+        return ProviderForm::configure($schema);
+    }
+
+    public static function table(Table $table): Table
+    {
+        return ProvidersTable::configure($table);
+    }
+
+    public static function getRelations(): array
+    {
+        return [
+            //
+        ];
+    }
+
+    public static function getPages(): array
+    {
+        return [
+            'index' => ListProviders::route('/'),
+            'create' => CreateProvider::route('/create'),
+            'edit' => EditProvider::route('/{record}/edit'),
+        ];
+    }
+}
diff --git a/app/Filament/Admin/Resources/Providers/Schemas/ProviderForm.php b/app/Filament/Admin/Resources/Providers/Schemas/ProviderForm.php
new file mode 100644
index 0000000..1ed68e8
--- /dev/null
+++ b/app/Filament/Admin/Resources/Providers/Schemas/ProviderForm.php
@@ -0,0 +1,44 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\Providers\Schemas;
+
+use Filament\Forms\Components\Textarea;
+use Filament\Forms\Components\TextInput;
+use Filament\Schemas\Components\Section;
+use Filament\Schemas\Schema;
+
+class ProviderForm
+{
+    public static function configure(Schema $schema): Schema
+    {
+        return $schema
+            ->components([
+                Section::make('Datos del Proveedor')
+                    ->schema([
+                        TextInput::make('name')
+                            ->label('Razón Social / Nombre')
+                            ->placeholder('Ej. Distribuidora Central C.A.')
+                            ->required()
+                            ->maxLength(255),
+                        TextInput::make('rif')
+                            ->label('RIF / Documento')
+                            ->placeholder('Ej. J-12345678-9')
+                            ->required()
+                            ->maxLength(20)
+                            ->unique(ignoreRecord: true),
+                        TextInput::make('phone')
+                            ->label('Teléfono de Contacto')
+                            ->placeholder('Ej. +58 412 1234567')
+                            ->tel()
+                            ->maxLength(50),
+                        Textarea::make('address')
+                            ->label('Dirección Fiscal')
+                            ->placeholder('Dirección completa del proveedor...')
+                            ->rows(3)
+                            ->columnSpanFull(),
+                    ])->columns(2),
+            ]);
+    }
+}
diff --git a/app/Filament/Admin/Resources/Providers/Tables/ProvidersTable.php b/app/Filament/Admin/Resources/Providers/Tables/ProvidersTable.php
new file mode 100644
index 0000000..2a2a3f6
--- /dev/null
+++ b/app/Filament/Admin/Resources/Providers/Tables/ProvidersTable.php
@@ -0,0 +1,58 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Resources\Providers\Tables;
+
+use Filament\Actions\BulkActionGroup;
+use Filament\Actions\DeleteBulkAction;
+use Filament\Actions\EditAction;
+use Filament\Tables\Columns\TextColumn;
+use Filament\Tables\Table;
+
+class ProvidersTable
+{
+    public static function configure(Table $table): Table
+    {
+        return $table
+            ->columns([
+                TextColumn::make('name')
+                    ->label('Razón Social / Nombre')
+                    ->searchable()
+                    ->sortable(),
+                TextColumn::make('rif')
+                    ->label('RIF')
+                    ->searchable()
+                    ->sortable(),
+                TextColumn::make('phone')
+                    ->label('Teléfono')
+                    ->searchable(),
+                TextColumn::make('expenses_count')
+                    ->counts('expenses')
+                    ->label('Nro. Gastos')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+                TextColumn::make('created_at')
+                    ->label('Creado el')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+                TextColumn::make('updated_at')
+                    ->label('Actualizado el')
+                    ->dateTime('d/m/Y H:i')
+                    ->sortable()
+                    ->toggleable(isToggledHiddenByDefault: true),
+            ])
+            ->filters([
+                //
+            ])
+            ->recordActions([
+                EditAction::make(),
+            ])
+            ->toolbarActions([
+                BulkActionGroup::make([
+                    DeleteBulkAction::make(),
+                ]),
+            ]);
+    }
+}
diff --git a/app/Filament/Admin/Resources/Sales/Schemas/SaleForm.php b/app/Filament/Admin/Resources/Sales/Schemas/SaleForm.php
index 6252879..fe0375b 100644
--- a/app/Filament/Admin/Resources/Sales/Schemas/SaleForm.php
+++ b/app/Filament/Admin/Resources/Sales/Schemas/SaleForm.php
@@ -1,20 +1,24 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Filament\Admin\Resources\Sales\Schemas;
 
+use App\Models\Product;
 use Filament\Forms\Components\Repeater;
 use Filament\Forms\Components\Select;
 use Filament\Forms\Components\TextInput;
+use Filament\Forms\Get;
+use Filament\Forms\Set;
 use Filament\Schemas\Components\Section;
 use Filament\Schemas\Schema;
-use App\Models\Product;
 
 class SaleForm
 {
     public static function configure(Schema $schema): Schema
     {
         return $schema
             ->components([
                 Section::make('Información de la Venta')
                     ->schema([
                         TextInput::make('invoice_number')
@@ -52,41 +56,59 @@ public static function configure(Schema $schema): Schema
                     ->schema([
                         Repeater::make('saleItems')
                             ->relationship()
                             ->schema([
                                 Select::make('product_id')
                                     ->label('Producto')
                                     ->relationship('product', 'name')
                                     ->searchable()
                                     ->preload()
                                     ->required()
-                                    ->reactive()
-                                    ->afterStateUpdated(fn ($state, callable $set) => $set('unit_price', Product::find($state)?->price ?? 0)),
+                                    ->live(onBlur: true)
+                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
+                                        $price = Product::find($state)?->price ?? 0;
+                                        $set('unit_price', $price);
+                                        $quantity = $get('quantity') ?? 1;
+                                        $set('subtotal', $price * $quantity);
+                                        self::updateTotals($get, $set);
+                                    }),
                                 TextInput::make('quantity')
                                     ->label('Cantidad')
                                     ->numeric()
                                     ->default(1)
                                     ->required()
-                                    ->reactive()
-                                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('subtotal', $state * $get('unit_price'))),
+                                    ->live(onBlur: true)
+                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
+                                        $price = $get('unit_price') ?? 0;
+                                        $set('subtotal', (float)$state * (float)$price);
+                                        self::updateTotals($get, $set);
+                                    }),
                                 TextInput::make('unit_price')
                                     ->label('Precio Unitario')
                                     ->numeric()
                                     ->required()
-                                    ->reactive()
-                                    ->afterStateUpdated(fn ($state, callable $get, callable $set) => $set('subtotal', $state * $get('quantity'))),
+                                    ->live(onBlur: true)
+                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
+                                        $quantity = $get('quantity') ?? 1;
+                                        $set('subtotal', (float)$state * (float)$quantity);
+                                        self::updateTotals($get, $set);
+                                    }),
                                 TextInput::make('subtotal')
                                     ->label('Subtotal')
                                     ->numeric()
                                     ->required()
                                     ->readOnly(),
                             ])
+                            ->live(onBlur: true)
+                            ->afterStateUpdated(function (Get $get, Set $set) {
+                                self::updateTotals($get, $set, true);
+                            })
                             ->columns(4)
                             ->defaultItems(1)
                     ])
                     ->columnSpan('full'),
                 
                 Section::make('Pagos Múltiples')
                     ->schema([
                         Repeater::make('payments')
                             ->relationship()
                             ->schema([
@@ -105,27 +127,64 @@ public static function configure(Schema $schema): Schema
                             ->defaultItems(1)
                     ])
                     ->columnSpan('full'),
                 
                 Section::make('Totales')
                     ->schema([
                         TextInput::make('total_base')
                             ->label('Base Imponible')
                             ->numeric()
                             ->default(0)
-                            ->required(),
+                            ->required()
+                            ->readOnly(),
                         TextInput::make('total_vat')
                             ->label('IVA')
                             ->numeric()
                             ->default(0)
-                            ->required(),
+                            ->required()
+                            ->readOnly(),
                         TextInput::make('total_amount')
                             ->label('Total General')
                             ->numeric()
                             ->default(0)
-                            ->required(),
+                            ->required()
+                            ->readOnly(),
                     ])
                     ->columns(3)
                     ->columnSpan('full'),
             ]);
     }
+
+    public static function updateTotals(Get $get, Set $set, bool $isRoot = false): void
+    {
+        $items = $isRoot ? $get('saleItems') : $get('../../saleItems');
+        $totalBase = 0.0;
+        $totalVat = 0.0;
+        $vatRate = (float) config('app.vat_rate', 0.16);
+        
+        if (is_array($items)) {
+            foreach ($items as $item) {
+                $subtotal = (float) ($item['subtotal'] ?? 0);
+                $totalBase += $subtotal;
+                
+                if (!empty($item['product_id'])) {
+                    $product = \App\Models\Product::find($item['product_id']);
+                    if ($product && $product->has_vat) {
+                        $totalVat += $subtotal * $vatRate;
+                    }
+                }
+            }
+        }
+        
+        $totalAmount = $totalBase + $totalVat;
+        
+        if ($isRoot) {
+            $set('total_base', number_format($totalBase, 2, '.', ''));
+            $set('total_vat', number_format($totalVat, 2, '.', ''));
+            $set('total_amount', number_format($totalAmount, 2, '.', ''));
+        } else {
+            $set('../../total_base', number_format($totalBase, 2, '.', ''));
+            $set('../../total_vat', number_format($totalVat, 2, '.', ''));
+            $set('../../total_amount', number_format($totalAmount, 2, '.', ''));
+        }
+    }
 }
diff --git a/app/Models/InventoryMovement.php b/app/Models/InventoryMovement.php
index c772bbb..3ad858c 100644
--- a/app/Models/InventoryMovement.php
+++ b/app/Models/InventoryMovement.php
@@ -1,25 +1,35 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Models;
 
 use Illuminate\Database\Eloquent\Model;
+use Illuminate\Database\Eloquent\Relations\BelongsTo;
+use Illuminate\Database\Eloquent\Relations\MorphTo;
 
 class InventoryMovement extends Model
 {
     protected $guarded = [];
 
-    public function product()
+    /**
+     * @return BelongsTo<Product, InventoryMovement>
+     */
+    public function product(): BelongsTo
     {
         return $this->belongsTo(Product::class);
     }
 
-    public function user()
+    /**
+     * @return BelongsTo<User, InventoryMovement>
+     */
+    public function user(): BelongsTo
     {
         return $this->belongsTo(User::class);
     }
 
-    public function reference()
+    public function reference(): MorphTo
     {
         return $this->morphTo();
     }
 }
diff --git a/app/Models/User.php b/app/Models/User.php
index 25ee3aa..13b12b4 100644
--- a/app/Models/User.php
+++ b/app/Models/User.php
@@ -1,30 +1,37 @@
 <?php
 
 namespace App\Models;
 
 // use Illuminate\Contracts\Auth\MustVerifyEmail;
 use Database\Factories\UserFactory;
+use Filament\Models\Contracts\FilamentUser;
+use Filament\Panel;
 use Illuminate\Database\Eloquent\Attributes\Fillable;
 use Illuminate\Database\Eloquent\Attributes\Hidden;
 use Illuminate\Database\Eloquent\Factories\HasFactory;
 use Illuminate\Foundation\Auth\User as Authenticatable;
 use Illuminate\Notifications\Notifiable;
 use Spatie\Permission\Traits\HasRoles;
 
 #[Fillable(['name', 'email', 'password'])]
 #[Hidden(['password', 'remember_token'])]
-class User extends Authenticatable
+class User extends Authenticatable implements FilamentUser
 {
     /** @use HasFactory<UserFactory> */
     use HasFactory, Notifiable, HasRoles;
 
+    public function canAccessPanel(Panel $panel): bool
+    {
+        return true;
+    }
+
     /**
      * Get the attributes that should be cast.
      *
      * @return array<string, string>
      */
     protected function casts(): array
     {
         return [
             'email_verified_at' => 'datetime',
             'password' => 'hashed',
diff --git a/app/Observers/InventoryMovementObserver.php b/app/Observers/InventoryMovementObserver.php
new file mode 100644
index 0000000..7ae9597
--- /dev/null
+++ b/app/Observers/InventoryMovementObserver.php
@@ -0,0 +1,57 @@
+<?php
+
+declare(strict_types=1);
+
+namespace App\Observers;
+
+use App\Models\InventoryMovement;
+
+class InventoryMovementObserver
+{
+    public function creating(InventoryMovement $movement): void
+    {
+        $product = $movement->product;
+
+        if ($movement->user_id === null && auth()->check()) {
+            $movement->user_id = auth()->id();
+        }
+
+        if ($movement->unit_cost === null) {
+            $movement->unit_cost = (float) ($product?->cost ?? 0);
+        }
+
+        if (empty($movement->concept)) {
+            $movement->concept = 'Movimiento manual de inventario';
+        }
+
+        if ($movement->stock_after_movement === null) {
+            $currentStock = (float) ($product?->stock ?? 0);
+            if ($movement->type === 'in') {
+                $movement->stock_after_movement = $currentStock + (float) $movement->quantity;
+            } elseif ($movement->type === 'out') {
+                $movement->stock_after_movement = max(0, $currentStock - (float) $movement->quantity);
+            } elseif ($movement->type === 'adjustment') {
+                $movement->stock_after_movement = (float) $movement->quantity;
+            } else {
+                $movement->stock_after_movement = $currentStock;
+            }
+        }
+    }
+
+    public function created(InventoryMovement $movement): void
+    {
+        $product = $movement->product;
+
+        if (! $product) {
+            return;
+        }
+
+        if ($movement->type === 'in') {
+            $product->increment('stock', $movement->quantity);
+        } elseif ($movement->type === 'out') {
+            $product->decrement('stock', $movement->quantity);
+        } elseif ($movement->type === 'adjustment') {
+            $product->update(['stock' => $movement->quantity]);
+        }
+    }
+}
diff --git a/app/Providers/AppServiceProvider.php b/app/Providers/AppServiceProvider.php
index 5f18fa9..f2a1024 100644
--- a/app/Providers/AppServiceProvider.php
+++ b/app/Providers/AppServiceProvider.php
@@ -1,26 +1,31 @@
 <?php
 
+declare(strict_types=1);
+
 namespace App\Providers;
 
+use App\Models\InventoryMovement;
 use App\Models\SaleItem;
+use App\Observers\InventoryMovementObserver;
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
+        InventoryMovement::observe(InventoryMovementObserver::class);
     }
 }
diff --git a/app/Providers/Filament/AdminPanelProvider.php b/app/Providers/Filament/AdminPanelProvider.php
index 638c65b..22919c1 100644
--- a/app/Providers/Filament/AdminPanelProvider.php
+++ b/app/Providers/Filament/AdminPanelProvider.php
@@ -17,20 +17,21 @@
 use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
 use Illuminate\Routing\Middleware\SubstituteBindings;
 use Illuminate\Session\Middleware\StartSession;
 use Illuminate\View\Middleware\ShareErrorsFromSession;
 
 class AdminPanelProvider extends PanelProvider
 {
     public function panel(Panel $panel): Panel
     {
         return $panel
+            ->default()
             ->id('admin')
             ->path('admin')
             ->viteTheme('resources/css/filament/admin/theme.css')
             ->login(\App\Filament\Pages\Auth\CustomLogin::class)
             ->registration()
             ->colors([
                 'primary' => \Filament\Support\Colors\Color::hex('#FF5F1F'),
                 'secondary' => \Filament\Support\Colors\Color::hex('#00FF94'),
                 'tertiary' => \Filament\Support\Colors\Color::hex('#A855F7'),
                 'danger' => \Filament\Support\Colors\Color::Rose,
diff --git a/docs/superpowers/plans/2026-08-28-accounting-reports.md b/docs/superpowers/plans/2026-08-28-accounting-reports.md
new file mode 100644
index 0000000..1b18d6b
Binary files /dev/null and b/docs/superpowers/plans/2026-08-28-accounting-reports.md differ
diff --git a/docs/superpowers/plans/2026-08-28-business-logic.md b/docs/superpowers/plans/2026-08-28-business-logic.md
new file mode 100644
index 0000000..e0345f7
--- /dev/null
+++ b/docs/superpowers/plans/2026-08-28-business-logic.md
@@ -0,0 +1,194 @@
+# Lógica de Negocio (Business Logic) Implementation Plan
+
+> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
+
+**Goal:** Completar el 100% de la lógica de negocio para que el sistema de ventas esté completamente funcional (Backend de POS, Recursos Faltantes y Cierre de Caja).
+
+**Architecture:** Se crearán los recursos de Filament faltantes para la gestión de datos básicos (Métodos de Pago, Tasa de Cambio). Luego se implementará el componente Livewire del POS (`App\Filament\Admin\Pages\Pos`) que enlaza la vista existente con la creación atómica de Ventas y Pagos. Finalmente se implementará el Cierre de Caja (Z-Report) para consolidar ingresos y egresos.
+
+**Tech Stack:** Laravel 11, Filament v3, Livewire, Tailwind CSS.
+
+**Spec:** N/A (Generado a partir del análisis del código actual).
+
+## Global Constraints
+
+- Sigue los estándares de código PSR-12.
+- Utiliza declaración estricta de tipos `declare(strict_types=1);` en archivos nuevos.
+- Maneja transacciones de base de datos (`DB::transaction`) cuando se creen múltiples registros dependientes (ej. Venta + Detalles + Pago).
+
+---
+
+### Task 1: Recursos de Gestión Básica (Exchange Rates & Payment Methods)
+
+Para que el POS funcione, necesitamos configurar la Tasa BCV y los métodos de pago. Las migraciones ya existen.
+
+**Files:**
+- Create: `app/Filament/Admin/Resources/ExchangeRateResource.php`
+- Create: `app/Filament/Admin/Resources/PaymentMethodResource.php`
+
+**Interfaces:**
+- Produces: Paneles de administración para que el usuario pueda registrar tasas de cambio y métodos de pago.
+
+- [ ] **Step 1: Crear Recurso ExchangeRate**
+Ejecutar el comando de Filament para generar el recurso basado en el modelo `ExchangeRate`.
+```bash
+php artisan make:filament-resource ExchangeRate --generate --panel=admin
+```
+
+- [ ] **Step 2: Configurar ExchangeRateResource**
+Asegurar que el formulario tenga `date` y `rate`.
+```php
+// En app/Filament/Admin/Resources/ExchangeRateResource.php
+public static function form(Form $form): Form
+{
+    return $form->schema([
+        Forms\Components\DatePicker::make('date')->required()->default(now()),
+        Forms\Components\TextInput::make('rate')->numeric()->required(),
+    ]);
+}
+```
+
+- [ ] **Step 3: Crear Recurso PaymentMethod**
+Ejecutar el comando para `PaymentMethod`.
+```bash
+php artisan make:filament-resource PaymentMethod --generate --panel=admin
+```
+
+- [ ] **Step 4: Configurar PaymentMethodResource**
+Asegurar que el formulario tenga `name` y `is_active`.
+```php
+// En app/Filament/Admin/Resources/PaymentMethodResource.php
+public static function form(Form $form): Form
+{
+    return $form->schema([
+        Forms\Components\TextInput::make('name')->required(),
+        Forms\Components\Toggle::make('is_active')->default(true),
+    ]);
+}
+```
+
+- [ ] **Step 5: Commit**
+```bash
+git add app/Filament/Admin/Resources/ExchangeRateResource.php app/Filament/Admin/Resources/PaymentMethodResource.php
+git commit -m "feat: add missing resources for exchange rates and payment methods"
+```
+
+### Task 2: Backend del Punto de Venta (POS Livewire Component)
+
+La vista `pos.blade.php` existe, pero falta el controlador Livewire para darle vida.
+
+**Files:**
+- Create: `app/Filament/Admin/Pages/Pos.php`
+- Modify: `resources/views/filament/admin/pages/pos.blade.php` (si es necesario enlazar variables).
+
+**Interfaces:**
+- Consumes: Modelos `Product`, `ExchangeRate`, `PaymentMethod`.
+- Produces: Interfaz funcional que permite buscar productos, añadirlos al carrito, y procesar la venta.
+
+- [ ] **Step 1: Crear la clase Pos**
+Crear el archivo `app/Filament/Admin/Pages/Pos.php` extendiendo de `Filament\Pages\Page`.
+```php
+<?php
+declare(strict_types=1);
+
+namespace App\Filament\Admin\Pages;
+
+use Filament\Pages\Page;
+use App\Models\Product;
+use App\Models\ExchangeRate;
+
+class Pos extends Page
+{
+    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
+    protected static string $view = 'filament.admin.pages.pos';
+    
+    public string $search = '';
+    public float $bcvRate = 0.0;
+    public array $cart = [];
+    public float $totalBase = 0.0;
+    
+    public function mount(): void
+    {
+        $rate = ExchangeRate::latest('date')->first();
+        $this->bcvRate = $rate ? (float) $rate->rate : 0.0;
+    }
+    
+    // ... agregar mÃ©todos addToCart, removeFromCart, processSale
+}
+```
+
+- [ ] **Step 3: Procesar Venta (Process Sale)**
+Crear la transacción que guarda la Venta y sus Ítems.
+```php
+public function processSale(): void
+{
+    if (empty($this->cart)) return;
+    
+    \DB::transaction(function () {
+        $sale = \App\Models\Sale::create([
+            'date' => now(),
+            'total_base' => $this->totalBase,
+            'total_vat' => $this->totalBase * 0.16, // Asumiendo 16%
+            'total_amount' => $this->totalBase * 1.16,
+            'status' => 'completed',
+        ]);
+        
+        foreach ($this->cart as $item) {
+            $sale->items()->create([
+                'product_id' => $item['id'],
+                'quantity' => $item['quantity'],
+                'unit_price' => $item['price'],
+            ]);
+        }
+    });
+    
+    $this->cart = [];
+    $this->calculateTotals();
+    \Filament\Notifications\Notification::make()->title('Venta completada')->success()->send();
+}
+```
+
+- [ ] **Step 4: Commit**
+```bash
+git add app/Filament/Admin/Pages/Pos.php
+git commit -m "feat: implement POS backend logic"
+```
+
+### Task 3: Lógica de Cierre de Caja (Z-Report)
+
+Crear el recurso para gestionar los Cortes Z diarios, calculando automáticamente las ventas del día.
+
+**Files:**
+- Create: `app/Filament/Admin/Resources/ZReportResource.php`
+
+**Interfaces:**
+- Consumes: Modelos `Sale`, `Expense`.
+- Produces: Panel para generar el cierre diario.
+
+- [ ] **Step 1: Crear Recurso ZReport**
+```bash
+php artisan make:filament-resource ZReport --generate --panel=admin
+```
+
+- [ ] **Step 2: Configurar Lógica de Cálculo en el Formulario**
+Al crear un Z-Report, se deben precargar los totales del día actual.
+```php
+// En app/Filament/Admin/Resources/ZReportResource.php
+public static function form(Form $form): Form
+{
+    return $form->schema([
+        Forms\Components\DatePicker::make('date')->default(now())->required(),
+        Forms\Components\TextInput::make('total_sales')
+            ->default(fn() => \App\Models\Sale::whereDate('date', today())->sum('total_amount')),
+        Forms\Components\TextInput::make('total_expenses')
+            ->default(fn() => \App\Models\Expense::whereDate('date', today())->sum('amount')),
+        // ... otros campos como cash_in_drawer
+    ]);
+}
+```
+
+- [ ] **Step 3: Commit**
+```bash
+git add app/Filament/Admin/Resources/ZReportResource.php
+git commit -m "feat: add Z-Report resource for daily closing"
+```
diff --git a/docs/superpowers/plans/2026-08-28-ventas-fixes.md b/docs/superpowers/plans/2026-08-28-ventas-fixes.md
new file mode 100644
index 0000000..2dff005
--- /dev/null
+++ b/docs/superpowers/plans/2026-08-28-ventas-fixes.md
@@ -0,0 +1,235 @@
+# Reparación de Errores Críticos Implementation Plan
+
+> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
+
+**Goal:** Solucionar los errores críticos identificados en la revisión de código (ExpensesTable roto, pérdida de datos por falta de campos fillables, deducción de inventario, y validación de totales).
+
+**Architecture:** Mantenemos la estructura actual de Filament pero agregamos un Observer para manejar la lógica de negocio (deducción de inventario) y sobrescribimos el ciclo de vida del recurso para garantizar el cálculo seguro de los totales.
+
+**Tech Stack:** PHP 8.3, Laravel 11, Filament v3
+
+**Spec:** `C:/Users/santiago/.gemini/antigravity-cli/brain/071365fb-6149-407e-a19b-fca2d6f3c3dc/review-ventas-declaracion.md`
+
+## Global Constraints
+
+- Sigue los estándares de código PSR-12.
+- Utiliza declaración estricta de tipos `declare(strict_types=1);` en archivos nuevos.
+
+---
+
+### Task 1: Fix ExpensesTable Resource
+
+**Files:**
+- Modify: `app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php:16-41`
+
+**Interfaces:**
+- Consumes: N/A
+- Produces: Correct table columns for Expense resource.
+
+- [ ] **Step 1: Update columns to match Expense model**
+
+Reemplaza las columnas problemáticas en `app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php`:
+
+```php
+                TextColumn::make('provider.name')
+                    ->label('Proveedor')
+                    ->sortable()
+                    ->searchable(),
+                TextColumn::make('invoice_number')
+                    ->label('Nro. Factura')
+                    ->searchable(),
+                TextColumn::make('total_amount')
+                    ->label('Monto Total')
+                    ->numeric()
+                    ->sortable(),
+                TextColumn::make('expense_date')
+                    ->label('Fecha de Gasto')
+                    ->date()
+                    ->sortable(),
+```
+
+- [ ] **Step 2: Commit**
+
+```bash
+git add app/Filament/Admin/Resources/Expenses/Tables/ExpensesTable.php
+git commit -m "fix: update ExpensesTable columns to match Expense model fields"
+```
+
+---
+
+### Task 2: Fix Missing Fillable Fields
+
+**Files:**
+- Modify: `app/Models/Sale.php:12-22`
+- Modify: `app/Models/Expense.php:12-22`
+
+**Interfaces:**
+- Consumes: N/A
+- Produces: Sale and Expense models that accept accounting dates.
+
+- [ ] **Step 1: Add date fields to Expense fillable array**
+
+En `app/Models/Expense.php`, agrega `'invoice_date'`, `'accounting_date'`, y `'due_date'` al array `$fillable`.
+
+```php
+    protected $fillable = [
+        'user_id',
+        'provider_id',
+        'invoice_number',
+        'control_number',
+        'description',
+        'total_base',
+        'total_vat',
+        'total_amount',
+        'expense_date',
+        'invoice_date',
+        'accounting_date',
+        'due_date',
+    ];
+```
+
+- [ ] **Step 2: Add date fields to Sale fillable array**
+
+En `app/Models/Sale.php`, agrega `'invoice_date'`, `'accounting_date'`, y `'due_date'` al array `$fillable`.
+
+```php
+    protected $fillable = [
+        'user_id',
+        'customer_id',
+        'invoice_number',
+        'total_base',
+        'total_vat',
+        'total_igtf',
+        'total_amount',
+        'status',
+        'invoice_date',
+        'accounting_date',
+        'due_date',
+    ];
+```
+
+- [ ] **Step 3: Commit**
+
+```bash
+git add app/Models/Expense.php app/Models/Sale.php
+git commit -m "fix: add missing accounting dates to fillable properties"
+```
+
+---
+
+### Task 3: Implement Inventory Deduction
+
+**Files:**
+- Create: `app/Observers/SaleItemObserver.php`
+- Modify: `app/Providers/AppServiceProvider.php`
+
+**Interfaces:**
+- Consumes: `SaleItem` model creation.
+- Produces: Deduction of `Product` stock.
+
+- [ ] **Step 1: Create the Observer**
+
+Ejecuta el comando para crear el Observer:
+```bash
+php artisan make:observer SaleItemObserver --model=SaleItem
+```
+
+- [ ] **Step 2: Implement stock deduction in created event**
+
+Abre `app/Observers/SaleItemObserver.php` e implementa el método `created`:
+
+```php
+<?php
+
+namespace App\Observers;
+
+use App\Models\SaleItem;
+use App\Models\Product;
+use Illuminate\Support\Facades\DB;
+
+class SaleItemObserver
+{
+    public function created(SaleItem $saleItem): void
+    {
+        DB::transaction(function () use ($saleItem) {
+            $product = $saleItem->product;
+            
+            if ($product) {
+                $product->stock -= $saleItem->quantity;
+                $product->save();
+            }
+        });
+    }
+}
+```
+
+- [ ] **Step 3: Register the Observer**
+
+Abre `app/Providers/AppServiceProvider.php` y regístralo en el método `boot`:
+
+```php
+use App\Models\SaleItem;
+use App\Observers\SaleItemObserver;
+
+// Dentro del método boot():
+public function boot(): void
+{
+    SaleItem::observe(SaleItemObserver::class);
+}
+```
+
+- [ ] **Step 4: Commit**
+
+```bash
+git add app/Observers/SaleItemObserver.php app/Providers/AppServiceProvider.php
+git commit -m "feat: implement SaleItemObserver to deduct product stock on sale"
+```
+
+---
+
+### Task 4: Secure Sale Totals Calculation
+
+**Files:**
+- Modify: `app/Filament/Admin/Resources/Sales/Pages/CreateSale.php`
+
+**Interfaces:**
+- Consumes: Form data before creation.
+- Produces: Re-calculated totals based on repeater items.
+
+- [ ] **Step 1: Override mutateFormDataBeforeCreate**
+
+En `app/Filament/Admin/Resources/Sales/Pages/CreateSale.php`, agrega el método `mutateFormDataBeforeCreate` para calcular matemáticamente los totales reales desde el backend:
+
+```php
+    protected function mutateFormDataBeforeCreate(array $data): array
+    {
+        $totalBase = 0;
+        $totalVat = 0;
+
+        if (isset($data['saleItems']) && is_array($data['saleItems'])) {
+            foreach ($data['saleItems'] as $item) {
+                $quantity = (float) ($item['quantity'] ?? 0);
+                $unitPrice = (float) ($item['unit_price'] ?? 0);
+                
+                $lineTotal = $quantity * $unitPrice;
+                $totalBase += $lineTotal;
+                
+                // Cálculo simple de IVA, asumiendo 16% si aplica
+                $totalVat += $lineTotal * 0.16; 
+            }
+        }
+
+        $data['total_base'] = $totalBase;
+        $data['total_vat'] = $totalVat;
+        $data['total_amount'] = $totalBase + $totalVat + (float) ($data['total_igtf'] ?? 0);
+
+        return $data;
+    }
+```
+
+- [ ] **Step 2: Commit**
+
+```bash
+git add app/Filament/Admin/Resources/Sales/Pages/CreateSale.php
+git commit -m "feat: securely calculate sale totals in backend before create"
+```
diff --git a/docs/superpowers/plans/plan-theme-modifications.md b/docs/superpowers/plans/plan-theme-modifications.md
new file mode 100644
index 0000000..c3dc21a
--- /dev/null
+++ b/docs/superpowers/plans/plan-theme-modifications.md
@@ -0,0 +1,37 @@
+# Plan: Implementación de Modo Claro y Oscuro
+
+Este plan identifica los pasos necesarios para soportar correctamente temas claro y oscuro en las vistas personalizadas de Filament, evitando colores fijos (hardcoded) y aprovechando el sistema de diseño nativo.
+
+## 1. Análisis del Problema Actual
+- **Colores estáticos:** Las vistas recientes (como `custom-dashboard.blade.php`) tienen colores fijos, por ejemplo, `bg-[#1A1A1A]` (oscuro) y texto `text-white`. Estos se ven bien en modo oscuro pero son ilegibles o incorrectos en modo claro.
+- **CSS no responsivo al tema:** En `resources/css/filament/admin/theme.css` se definieron variables para `.dark`, pero las vistas Blade no están utilizando las utilidades de Tailwind (`dark:`) ni las variables CSS correctamente para alternar.
+
+## 2. Objetivos
+- Permitir que el usuario cambie entre modo claro y oscuro desde el panel de Filament.
+- Asegurar que todas las vistas (Dashboard, POS, Centro de Transcripción) respondan correctamente al tema activo.
+- Mantener los colores de la marca (`#FF5F1F`, `#00FF94`) intactos.
+
+## 3. Pasos de Implementación
+
+### Tarea 1: Refactorizar las Vistas Blade (Dashboard, POS, etc.)
+Reemplazar los colores hardcoded por clases de Tailwind que soporten `dark:`.
+- Cambiar `bg-[#1A1A1A]` por clases dinámicas como `bg-white dark:bg-gray-900`.
+- Cambiar `border-[#2A2A2A]` por `border-gray-200 dark:border-gray-800`.
+- Cambiar `text-white` por `text-gray-900 dark:text-white`.
+- Reemplazar textos grises como `text-gray-400` por `text-gray-500 dark:text-gray-400`.
+
+### Tarea 2: Actualizar la Configuración de Tailwind y CSS
+- Revisar `resources/css/filament/admin/theme.css` para asegurar que las variables de la paleta clara y oscura estén correctamente balanceadas si se requiere sobrescribir los valores por defecto de Tailwind.
+- Opcionalmente, agregar los colores personalizados al `tailwind.config.js` para usarlos como `bg-brand-primary` en lugar de `bg-[#FF5F1F]`.
+
+### Tarea 3: Verificación de Filament Admin Panel
+- Asegurar que `AdminPanelProvider.php` no esté forzando el modo oscuro, y que el botón de toggle de tema de Filament esté habilitado (normalmente lo está por defecto).
+
+### Tarea 4: Recompilar y Pruebas
+- Ejecutar `npm run build` para generar el CSS final.
+- Probar la interfaz visualmente alternando entre tema claro y oscuro en el panel de administración.
+
+---
+
+**Estado:** ESPERANDO APROBACIÓN
+**Siguiente Paso:** Cuando estés listo, indícame ejecutar el plan o envíame tu siguiente comando.
diff --git a/resources/views/filament/admin/pages/tax-report.blade.php b/resources/views/filament/admin/pages/tax-report.blade.php
new file mode 100644
index 0000000..1ab4d94
--- /dev/null
+++ b/resources/views/filament/admin/pages/tax-report.blade.php
@@ -0,0 +1,400 @@
+<x-filament-panels::page>
+    <div class="flex flex-col h-full -mt-4 text-gray-200 antialiased font-['Hanken_Grotesk'] space-y-6">
+        
+        <!-- ========================================== -->
+        <!-- 1. HEADER CON SELECTORES DE FECHA Y PRINT  -->
+        <!-- ========================================== -->
+        <header class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-[#1A1A1A] p-6 rounded-2xl border border-[#2A2A2A]">
+            <div>
+                <div class="flex items-center gap-2.5">
+                    <div class="w-10 h-10 rounded-2xl bg-[#00FF94]/15 text-[#00FF94] flex items-center justify-center shrink-0">
+                        <x-heroicon-o-calculator class="w-6 h-6" />
+                    </div>
+                    <div>
+                        <h1 class="text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">Declaración de Impuestos</h1>
+                        <p class="text-[#9E9E9E] text-xs sm:text-sm mt-0.5 font-['Hanken_Grotesk']">Auditoría de Débito Fiscal (IVA), Ventas e Información Contable del período.</p>
+                    </div>
+                </div>
+            </div>
+            
+            <div class="flex flex-wrap items-center gap-3">
+                <!-- Selector de Mes -->
+                <div class="flex items-center gap-2 bg-[#121212] px-3 py-1.5 rounded-full border border-[#2A2A2A]">
+                    <span class="text-xs text-[#9E9E9E] font-medium pl-1">Mes:</span>
+                    <select 
+                        wire:model.live="selectedMonth" 
+                        class="bg-transparent text-white text-xs font-semibold focus:outline-none border-none cursor-pointer py-1 pr-6"
+                    >
+                        @foreach($months as $num => $name)
+                            <option value="{{ $num }}" class="bg-[#1A1A1A] text-white">{{ $name }}</option>
+                        @endforeach
+                    </select>
+                </div>
+
+                <!-- Selector de Año -->
+                <div class="flex items-center gap-2 bg-[#121212] px-3 py-1.5 rounded-full border border-[#2A2A2A]">
+                    <span class="text-xs text-[#9E9E9E] font-medium pl-1">Año:</span>
+                    <select 
+                        wire:model.live="selectedYear" 
+                        class="bg-transparent text-white text-xs font-semibold focus:outline-none border-none cursor-pointer py-1 pr-6"
+                    >
+                        @foreach($years as $yearVal)
+                            <option value="{{ $yearVal }}" class="bg-[#1A1A1A] text-white">{{ $yearVal }}</option>
+                        @endforeach
+                    </select>
+                </div>
+
+                <!-- Botón Imprimir / Exportar -->
+                <button 
+                    type="button"
+                    onclick="window.print()"
+                    class="inline-flex items-center gap-2 bg-[#2A2A2A] hover:bg-[#3A3A3A] text-white px-4 py-2 rounded-full text-xs font-semibold transition-all border border-[#3A3A3A]"
+                    title="Imprimir informe fiscal"
+                >
+                    <x-heroicon-o-printer class="w-4 h-4 text-[#9E9E9E]" />
+                    <span>Imprimir</span>
+                </button>
+            </div>
+        </header>
+
+        <!-- ========================================== -->
+        <!-- 2. CUATRO TARJETAS PRINCIPALES DE TOTALES   -->
+        <!-- ========================================== -->
+        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
+            
+            <!-- Card 1: Total Ventas (Base) -->
+            <div class="bg-[#1A1A1A] rounded-2xl p-6 border border-[#2A2A2A] relative overflow-hidden group hover:border-[#3A3A3A] transition-all">
+                <div class="flex justify-between items-center mb-4">
+                    <span class="text-xs font-semibold uppercase tracking-wider text-[#9E9E9E]">Total Ventas (Base)</span>
+                    <div class="w-10 h-10 rounded-full bg-[#FF5F1F]/15 flex items-center justify-center text-[#FF5F1F]">
+                        <x-heroicon-o-shopping-bag class="w-5 h-5" />
+                    </div>
+                </div>
+                <div class="space-y-1">
+                    <h2 class="text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">
+                        ${{ number_format($totalSalesBase, 2) }}
+                    </h2>
+                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk'] text-[#9E9E9E]">
+                        <span class="inline-flex items-center gap-1 font-semibold text-[#FF5F1F]">
+                            {{ $salesCount }} {{ $salesCount === 1 ? 'factura emitida' : 'facturas emitidas' }}
+                        </span>
+                    </div>
+                </div>
+                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#FF5F1F] to-transparent opacity-60"></div>
+            </div>
+
+            <!-- Card 2: IVA Cobrado (Débito Fiscal) -->
+            <div class="bg-[#1A1A1A] rounded-2xl p-6 border border-[#2A2A2A] relative overflow-hidden group hover:border-[#3A3A3A] transition-all">
+                <div class="flex justify-between items-center mb-4">
+                    <span class="text-xs font-semibold uppercase tracking-wider text-[#9E9E9E]">IVA Cobrado (Débito)</span>
+                    <div class="w-10 h-10 rounded-full bg-[#A855F7]/15 flex items-center justify-center text-[#A855F7]">
+                        <x-heroicon-o-receipt-percent class="w-5 h-5" />
+                    </div>
+                </div>
+                <div class="space-y-1">
+                    <h2 class="text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">
+                        ${{ number_format($totalSalesVat, 2) }}
+                    </h2>
+                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk'] text-[#9E9E9E]">
+                        <span class="inline-flex items-center gap-1 font-semibold text-[#A855F7]">
+                            16% IVA Débito Fiscal
+                        </span>
+                    </div>
+                </div>
+                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#A855F7] to-transparent opacity-60"></div>
+            </div>
+
+            <!-- Card 3: Total Gastos (Egresos) -->
+            <div class="bg-[#1A1A1A] rounded-2xl p-6 border border-[#2A2A2A] relative overflow-hidden group hover:border-[#3A3A3A] transition-all">
+                <div class="flex justify-between items-center mb-4">
+                    <span class="text-xs font-semibold uppercase tracking-wider text-[#9E9E9E]">Total Gastos (Egresos)</span>
+                    <div class="w-10 h-10 rounded-full bg-rose-500/15 flex items-center justify-center text-rose-400">
+                        <x-heroicon-o-credit-card class="w-5 h-5" />
+                    </div>
+                </div>
+                <div class="space-y-1">
+                    <h2 class="text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight">
+                        ${{ number_format($totalExpenses, 2) }}
+                    </h2>
+                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk'] text-[#9E9E9E]">
+                        <span class="inline-flex items-center gap-1 font-medium text-rose-400">
+                            {{ $expensesCount }} {{ $expensesCount === 1 ? 'registro de gasto' : 'registros de gasto' }}
+                        </span>
+                    </div>
+                </div>
+                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-rose-500 to-transparent opacity-60"></div>
+            </div>
+
+            <!-- Card 4: Total a Declarar (IVA Neto) -->
+            <div class="bg-[#1A1A1A] rounded-2xl p-6 border border-[#2A2A2A] relative overflow-hidden group hover:border-[#3A3A3A] transition-all">
+                <div class="flex justify-between items-center mb-4">
+                    <span class="text-xs font-semibold uppercase tracking-wider text-[#9E9E9E]">Total a Declarar (IVA)</span>
+                    <div class="w-10 h-10 rounded-full bg-[#00FF94]/15 flex items-center justify-center text-[#00FF94]">
+                        <x-heroicon-o-check-badge class="w-5 h-5" />
+                    </div>
+                </div>
+                <div class="space-y-1">
+                    <h2 class="text-white text-2xl sm:text-3xl font-extrabold font-['Manrope'] tracking-tight text-[#00FF94]">
+                        ${{ number_format($netTaxToDeclare, 2) }}
+                    </h2>
+                    <div class="flex items-center gap-2 text-xs pt-1.5 font-['Hanken_Grotesk']">
+                        <span class="inline-flex items-center gap-1 font-semibold text-[#00FF94] bg-[#00FF94]/10 px-2 py-0.5 rounded-full border border-[#00FF94]/25">
+                            IVA Neto Exigible
+                        </span>
+                    </div>
+                </div>
+                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-[#00FF94] to-transparent opacity-60"></div>
+            </div>
+
+        </div>
+
+        <!-- ========================================== -->
+        <!-- 3. CUADRO DE RESUMEN TRIBUTARIO Y AUDITORÍA-->
+        <!-- ========================================== -->
+        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
+            
+            <!-- Resumen Fiscal Detallado -->
+            <div class="lg:col-span-2 bg-[#1A1A1A] rounded-2xl p-6 border border-[#2A2A2A] space-y-4">
+                <div class="flex items-center justify-between border-b border-[#2A2A2A] pb-4">
+                    <div>
+                        <h3 class="text-white text-lg font-bold font-['Manrope']">Resumen de Liquidación Tributaria</h3>
+                        <p class="text-xs text-[#9E9E9E] mt-0.5">Cálculo consolidado para la declaración mensual ante la administración tributaria.</p>
+                    </div>
+                    <span class="text-xs font-bold text-[#FF5F1F] bg-[#FF5F1F]/10 border border-[#FF5F1F]/25 px-3 py-1 rounded-full">
+                        {{ $periodName }}
+                    </span>
+                </div>
+
+                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
+                    <div class="p-4 rounded-xl bg-[#121212] border border-[#2A2A2A] space-y-1">
+                        <span class="text-xs text-[#9E9E9E] font-medium">Facturación Bruta Total (Ventas + IVA)</span>
+                        <p class="text-lg font-bold text-white font-['Manrope']">${{ number_format($totalSalesAmount, 2) }}</p>
+                    </div>
+
+                    <div class="p-4 rounded-xl bg-[#121212] border border-[#2A2A2A] space-y-1">
+                        <span class="text-xs text-[#9E9E9E] font-medium">Base Imponible Gravada</span>
+                        <p class="text-lg font-bold text-[#FF5F1F] font-['Manrope']">${{ number_format($totalSalesBase, 2) }}</p>
+                    </div>
+
+                    <div class="p-4 rounded-xl bg-[#121212] border border-[#2A2A2A] space-y-1">
+                        <span class="text-xs text-[#9E9E9E] font-medium">Débito Fiscal Generado (16%)</span>
+                        <p class="text-lg font-bold text-[#A855F7] font-['Manrope']">${{ number_format($totalSalesVat, 2) }}</p>
+                    </div>
+
+                    <div class="p-4 rounded-xl bg-[#121212] border border-[#2A2A2A] space-y-1">
+                        <span class="text-xs text-[#9E9E9E] font-medium">Total Gastos y Compras Operativas</span>
+                        <p class="text-lg font-bold text-rose-400 font-['Manrope']">${{ number_format($totalExpenses, 2) }}</p>
+                    </div>
+                </div>
+
+                <div class="p-4 rounded-xl bg-[#141414] border border-[#2A2A2A] flex flex-col sm:flex-row justify-between sm:items-center gap-2">
+                    <div>
+                        <span class="text-xs text-[#9E9E9E] font-medium">Resultado Operativo del Período (Base Ventas - Gastos)</span>
+                        <p class="text-xs text-[#6E6E6E]">Margen operativo antes de impuestos para el mes de {{ $periodName }}.</p>
+                    </div>
+                    <span class="text-xl font-extrabold font-['Manrope'] {{ $netBalance >= 0 ? 'text-[#00FF94]' : 'text-rose-400' }}">
+                        ${{ number_format($netBalance, 2) }}
+                    </span>
+                </div>
+            </div>
+
+            <!-- Tarjeta de Estado SENIAT / Certificación -->
+            <div class="bg-[#1A1A1A] rounded-2xl p-6 border border-[#2A2A2A] flex flex-col justify-between">
+                <div>
+                    <div class="flex items-center gap-3 mb-4">
+                        <div class="w-10 h-10 rounded-2xl bg-[#00FF94]/15 text-[#00FF94] flex items-center justify-center shrink-0">
+                            <x-heroicon-o-shield-check class="w-6 h-6" />
+                        </div>
+                        <div>
+                            <h3 class="text-white text-base font-bold font-['Manrope']">Estado de Auditoría</h3>
+                            <p class="text-xs text-[#9E9E9E]">Verificación contable lista</p>
+                        </div>
+                    </div>
+
+                    <div class="space-y-3 text-xs text-[#9E9E9E] pt-2">
+                        <div class="flex justify-between items-center py-2 border-b border-[#2A2A2A]">
+                            <span>Período Auditado:</span>
+                            <span class="font-bold text-white">{{ $periodName }}</span>
+                        </div>
+                        <div class="flex justify-between items-center py-2 border-b border-[#2A2A2A]">
+                            <span>Ventas Procesadas:</span>
+                            <span class="font-bold text-white">{{ $salesCount }}</span>
+                        </div>
+                        <div class="flex justify-between items-center py-2 border-b border-[#2A2A2A]">
+                            <span>Gastos Contabilizados:</span>
+                            <span class="font-bold text-white">{{ $expensesCount }}</span>
+                        </div>
+                        <div class="flex justify-between items-center py-2">
+                            <span>Impuesto a Enterar:</span>
+                            <span class="font-bold text-[#00FF94] font-['Manrope'] text-sm">${{ number_format($netTaxToDeclare, 2) }}</span>
+                        </div>
+                    </div>
+                </div>
+
+                <div class="pt-6 border-t border-[#2A2A2A] mt-4">
+                    <div class="p-3 bg-[#121212] rounded-xl border border-[#2A2A2A] text-[11px] text-[#9E9E9E] flex items-center gap-2">
+                        <x-heroicon-o-information-circle class="w-5 h-5 text-[#FF5F1F] shrink-0" />
+                        <span>Este reporte resume los Débitos Fiscales generados y los Egresos cargados durante el mes seleccionado.</span>
+                    </div>
+                </div>
+            </div>
+
+        </div>
+
+        <!-- ========================================== -->
+        <!-- 4. PESTAÑAS DE FACTURAS Y GASTOS AUDITADOS  -->
+        <!-- ========================================== -->
+        <div x-data="{ auditTab: 'ventas' }" class="bg-[#1A1A1A] rounded-2xl border border-[#2A2A2A] overflow-hidden shadow-sm">
+            
+            <!-- Encabezado con Tabs -->
+            <div class="p-6 border-b border-[#2A2A2A] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
+                <div>
+                    <h3 class="text-white text-lg font-bold font-['Manrope']">Documentos Fiscales del Período</h3>
+                    <p class="text-xs text-[#9E9E9E] mt-0.5">Auditoría detallada de los registros que componen el cálculo fiscal de {{ $periodName }}.</p>
+                </div>
+
+                <!-- Tabs Pill Style -->
+                <div class="inline-flex p-1 bg-[#121212] border border-[#2A2A2A] rounded-full self-start">
+                    <button 
+                        type="button"
+                        x-on:click="auditTab = 'ventas'"
+                        :class="auditTab === 'ventas' ? 'bg-[#FF5F1F] text-white shadow-md shadow-[#FF5F1F]/20' : 'text-[#9E9E9E] hover:text-white'"
+                        class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5"
+                    >
+                        <x-heroicon-o-shopping-bag class="w-3.5 h-3.5" />
+                        <span>Ventas ({{ $recentSales->count() }})</span>
+                    </button>
+
+                    <button 
+                        type="button"
+                        x-on:click="auditTab = 'gastos'"
+                        :class="auditTab === 'gastos' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/20' : 'text-[#9E9E9E] hover:text-white'"
+                        class="px-4 py-1.5 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5"
+                    >
+                        <x-heroicon-o-credit-card class="w-3.5 h-3.5" />
+                        <span>Gastos ({{ $recentExpenses->count() }})</span>
+                    </button>
+                </div>
+            </div>
+
+            <!-- Tab 1: Tabla de Ventas -->
+            <div x-show="auditTab === 'ventas'" class="overflow-x-auto">
+                <table class="w-full text-left font-['Hanken_Grotesk'] text-sm">
+                    <thead class="bg-[#141414] text-[#9E9E9E] uppercase text-[11px] font-semibold tracking-wider border-b border-[#2A2A2A]">
+                        <tr>
+                            <th scope="col" class="px-6 py-4">Nro. Factura</th>
+                            <th scope="col" class="px-6 py-4">Cliente</th>
+                            <th scope="col" class="px-6 py-4">Fecha Factura</th>
+                            <th scope="col" class="px-6 py-4 text-right">Base Imponible</th>
+                            <th scope="col" class="px-6 py-4 text-right">IVA (16%)</th>
+                            <th scope="col" class="px-6 py-4 text-right">Total Factura</th>
+                        </tr>
+                    </thead>
+                    <tbody class="divide-y divide-[#2A2A2A]">
+                        @forelse($recentSales as $sale)
+                            <tr class="hover:bg-[#222222]/50 transition-colors">
+                                <td class="px-6 py-4 whitespace-nowrap font-medium text-white">
+                                    <span class="inline-flex items-center gap-1.5">
+                                        <x-heroicon-o-document-text class="w-4 h-4 text-[#FF5F1F]" />
+                                        {{ $sale->invoice_number ?? '#TRX-' . str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT) }}
+                                    </span>
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-white font-medium">
+                                    {{ $sale->customer?->name ?? 'Cliente General' }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-[#9E9E9E] text-xs">
+                                    {{ $sale->invoice_date ?? $sale->created_at->format('Y-m-d') }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-white font-['Manrope']">
+                                    ${{ number_format((float) $sale->total_base, 2) }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-right font-bold text-[#A855F7] font-['Manrope']">
+                                    ${{ number_format((float) $sale->total_vat, 2) }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-right font-extrabold text-white font-['Manrope']">
+                                    ${{ number_format((float) $sale->total_amount, 2) }}
+                                </td>
+                            </tr>
+                        @empty
+                            <tr>
+                                <td colspan="6" class="px-6 py-12 text-center text-[#9E9E9E] text-xs">
+                                    <div class="flex flex-col items-center justify-center gap-2">
+                                        <x-heroicon-o-document-magnifying-glass class="w-8 h-8 text-[#6E6E6E]" />
+                                        <span>No se encontraron facturas de venta para el período {{ $periodName }}.</span>
+                                    </div>
+                                </td>
+                            </tr>
+                        @endforelse
+                    </tbody>
+                </table>
+            </div>
+
+            <!-- Tab 2: Tabla de Gastos -->
+            <div x-show="auditTab === 'gastos'" x-cloak class="overflow-x-auto">
+                <table class="w-full text-left font-['Hanken_Grotesk'] text-sm">
+                    <thead class="bg-[#141414] text-[#9E9E9E] uppercase text-[11px] font-semibold tracking-wider border-b border-[#2A2A2A]">
+                        <tr>
+                            <th scope="col" class="px-6 py-4">Nro. Factura / Control</th>
+                            <th scope="col" class="px-6 py-4">Proveedor</th>
+                            <th scope="col" class="px-6 py-4">Concepto</th>
+                            <th scope="col" class="px-6 py-4">Fecha Gasto</th>
+                            <th scope="col" class="px-6 py-4 text-right">Base Imponible</th>
+                            <th scope="col" class="px-6 py-4 text-right">IVA</th>
+                            <th scope="col" class="px-6 py-4 text-right">Monto Total</th>
+                        </tr>
+                    </thead>
+                    <tbody class="divide-y divide-[#2A2A2A]">
+                        @forelse($recentExpenses as $expense)
+                            <tr class="hover:bg-[#222222]/50 transition-colors">
+                                <td class="px-6 py-4 whitespace-nowrap font-medium text-white">
+                                    <span class="inline-flex items-center gap-1.5">
+                                        <x-heroicon-o-receipt-percent class="w-4 h-4 text-rose-400" />
+                                        {{ $expense->invoice_number ?? '#EXP-' . $expense->id }}
+                                        @if($expense->control_number)
+                                            <span class="text-[10px] text-[#6E6E6E]">({{ $expense->control_number }})</span>
+                                        @endif
+                                    </span>
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-white font-medium">
+                                    {{ $expense->provider?->name ?? 'Proveedor no asignado' }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-[#9E9E9E] text-xs truncate max-w-xs">
+                                    {{ $expense->description ?? 'Sin descripción' }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-[#9E9E9E] text-xs">
+                                    {{ $expense->expense_date?->format('Y-m-d') ?? $expense->invoice_date ?? $expense->created_at->format('Y-m-d') }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-white font-['Manrope']">
+                                    ${{ number_format((float) ($expense->total_base ?? 0), 2) }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium text-[#A855F7] font-['Manrope']">
+                                    ${{ number_format((float) ($expense->total_vat ?? 0), 2) }}
+                                </td>
+                                <td class="px-6 py-4 whitespace-nowrap text-right font-extrabold text-rose-400 font-['Manrope']">
+                                    ${{ number_format((float) $expense->total_amount, 2) }}
+                                </td>
+                            </tr>
+                        @empty
+                            <tr>
+                                <td colspan="7" class="px-6 py-12 text-center text-[#9E9E9E] text-xs">
+                                    <div class="flex flex-col items-center justify-center gap-2">
+                                        <x-heroicon-o-document-magnifying-glass class="w-8 h-8 text-[#6E6E6E]" />
+                                        <span>No se encontraron registros de gastos para el período {{ $periodName }}.</span>
+                                    </div>
+                                </td>
+                            </tr>
+                        @endforelse
+                    </tbody>
+                </table>
+            </div>
+
+            <!-- Footer de la Tabla -->
+            <div class="p-4 border-t border-[#2A2A2A] flex items-center justify-between text-xs text-[#9E9E9E]">
+                <span>Mostrando registros de auditoría para el período fiscal seleccionado.</span>
+                <span class="font-medium text-white">{{ $periodName }}</span>
+            </div>
+
+        </div>
+
+    </div>
+</x-filament-panels::page>
diff --git a/tests/Feature/BaseResourcesTest.php b/tests/Feature/BaseResourcesTest.php
new file mode 100644
index 0000000..aab8876
--- /dev/null
+++ b/tests/Feature/BaseResourcesTest.php
@@ -0,0 +1,80 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Models\ExchangeRate;
+use App\Models\PaymentMethod;
+use App\Models\Provider;
+use App\Models\User;
+use Illuminate\Foundation\Testing\RefreshDatabase;
+
+uses(RefreshDatabase::class);
+
+test('authenticated user can view payment methods index and create page', function () {
+    $user = User::factory()->create();
+    $method = PaymentMethod::create([
+        'name' => 'Pago Móvil Banesco',
+        'requires_reference' => true,
+        'applies_igtf' => false,
+        'is_active' => true,
+    ]);
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.payment-methods.index'))
+        ->assertSuccessful()
+        ->assertSee('Pago Móvil Banesco');
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.payment-methods.create'))
+        ->assertSuccessful();
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.payment-methods.edit', ['record' => $method]))
+        ->assertSuccessful();
+});
+
+test('authenticated user can view providers index and create page', function () {
+    $user = User::factory()->create();
+    $provider = Provider::create([
+        'name' => 'Distribuidora Central C.A.',
+        'rif' => 'J-12345678-0',
+        'phone' => '04121234567',
+        'address' => 'Caracas, Venezuela',
+    ]);
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.providers.index'))
+        ->assertSuccessful()
+        ->assertSee('Distribuidora Central C.A.')
+        ->assertSee('J-12345678-0');
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.providers.create'))
+        ->assertSuccessful();
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.providers.edit', ['record' => $provider]))
+        ->assertSuccessful();
+});
+
+test('authenticated user can view exchange rates index and create page', function () {
+    $user = User::factory()->create();
+    $rate = ExchangeRate::create([
+        'currency' => 'USD',
+        'rate' => 36.500000,
+        'date_published' => now(),
+    ]);
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.exchange-rates.index'))
+        ->assertSuccessful()
+        ->assertSee('USD');
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.exchange-rates.create'))
+        ->assertSuccessful();
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.exchange-rates.edit', ['record' => $rate]))
+        ->assertSuccessful();
+});
diff --git a/tests/Feature/ExampleTest.php b/tests/Feature/ExampleTest.php
index 8fdc86b..d0c902b 100644
--- a/tests/Feature/ExampleTest.php
+++ b/tests/Feature/ExampleTest.php
@@ -1,7 +1,7 @@
 <?php
 
 test('the application returns a successful response', function () {
     $response = $this->get('/');
 
-    $response->assertStatus(200);
+    $response->assertRedirect(route('filament.admin.auth.login'));
 });
diff --git a/tests/Feature/InventoryMovementTest.php b/tests/Feature/InventoryMovementTest.php
new file mode 100644
index 0000000..cb03201
--- /dev/null
+++ b/tests/Feature/InventoryMovementTest.php
@@ -0,0 +1,119 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Models\InventoryMovement;
+use App\Models\Product;
+use App\Models\User;
+use Illuminate\Foundation\Testing\RefreshDatabase;
+
+uses(RefreshDatabase::class);
+
+test('authenticated user can view inventory movements index and create page', function () {
+    $user = User::factory()->create();
+    $product = Product::create([
+        'name' => 'Harina PAN 1kg',
+        'description' => 'Harina de maíz precocida',
+        'price' => 1.50,
+        'cost' => 1.00,
+        'stock' => 10,
+        'has_vat' => true,
+    ]);
+
+    $movement = InventoryMovement::create([
+        'product_id' => $product->id,
+        'user_id' => $user->id,
+        'type' => 'in',
+        'concept' => 'Compra inicial',
+        'quantity' => 20,
+        'unit_cost' => 1.00,
+        'stock_after_movement' => 30,
+    ]);
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.inventory-movements.index'))
+        ->assertSuccessful()
+        ->assertSee('Harina PAN 1kg')
+        ->assertSee('Compra inicial');
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.inventory-movements.create'))
+        ->assertSuccessful();
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.resources.inventory-movements.view', ['record' => $movement]))
+        ->assertSuccessful();
+});
+
+test('creating an "in" inventory movement increments product stock and calculates stock_after_movement', function () {
+    $user = User::factory()->create();
+    $this->actingAs($user);
+
+    $product = Product::create([
+        'name' => 'Arroz Mary 1kg',
+        'price' => 1.80,
+        'cost' => 1.20,
+        'stock' => 15,
+        'has_vat' => false,
+    ]);
+
+    $movement = InventoryMovement::create([
+        'product_id' => $product->id,
+        'type' => 'in',
+        'concept' => 'Recepcion de mercancia',
+        'quantity' => 25,
+    ]);
+
+    expect((int) $product->fresh()->stock)->toBe(40)
+        ->and((float) $movement->fresh()->stock_after_movement)->toBe(40.0)
+        ->and((float) $movement->fresh()->unit_cost)->toBe(1.20)
+        ->and($movement->fresh()->user_id)->toBe($user->id);
+});
+
+test('creating an "out" inventory movement decrements product stock and calculates stock_after_movement', function () {
+    $user = User::factory()->create();
+    $this->actingAs($user);
+
+    $product = Product::create([
+        'name' => 'Aceite Mazeite 1L',
+        'price' => 3.50,
+        'cost' => 2.50,
+        'stock' => 50,
+        'has_vat' => true,
+    ]);
+
+    $movement = InventoryMovement::create([
+        'product_id' => $product->id,
+        'type' => 'out',
+        'concept' => 'Merma por producto dañado',
+        'quantity' => 5,
+    ]);
+
+    expect((int) $product->fresh()->stock)->toBe(45)
+        ->and((float) $movement->fresh()->stock_after_movement)->toBe(45.0)
+        ->and($movement->fresh()->user_id)->toBe($user->id);
+});
+
+test('creating an "adjustment" inventory movement sets product stock directly and calculates stock_after_movement', function () {
+    $user = User::factory()->create();
+    $this->actingAs($user);
+
+    $product = Product::create([
+        'name' => 'Cafe Fama de America 500g',
+        'price' => 5.00,
+        'cost' => 3.50,
+        'stock' => 20,
+        'has_vat' => true,
+    ]);
+
+    $movement = InventoryMovement::create([
+        'product_id' => $product->id,
+        'type' => 'adjustment',
+        'concept' => 'Ajuste de inventario fisico',
+        'quantity' => 18,
+    ]);
+
+    expect((int) $product->fresh()->stock)->toBe(18)
+        ->and((float) $movement->fresh()->stock_after_movement)->toBe(18.0)
+        ->and($movement->fresh()->user_id)->toBe($user->id);
+});
diff --git a/tests/Feature/TaxReportTest.php b/tests/Feature/TaxReportTest.php
new file mode 100644
index 0000000..0afaa0b
--- /dev/null
+++ b/tests/Feature/TaxReportTest.php
@@ -0,0 +1,150 @@
+<?php
+
+declare(strict_types=1);
+
+use App\Filament\Admin\Pages\TaxReport;
+use App\Models\Customer;
+use App\Models\Expense;
+use App\Models\Provider;
+use App\Models\Sale;
+use App\Models\User;
+use Illuminate\Foundation\Testing\RefreshDatabase;
+use Livewire\Livewire;
+
+uses(RefreshDatabase::class);
+
+test('authenticated user can view tax report page', function () {
+    $user = User::factory()->create();
+
+    $this->actingAs($user)
+        ->get(route('filament.admin.pages.tax-report'))
+        ->assertSuccessful()
+        ->assertSee('Declaración de Impuestos')
+        ->assertSee('Total Ventas (Base)')
+        ->assertSee('IVA Cobrado (Débito)')
+        ->assertSee('Total Gastos (Egresos)')
+        ->assertSee('Total a Declarar (IVA)');
+});
+
+test('tax report page navigation attributes are properly configured', function () {
+    expect(TaxReport::getNavigationGroup())->toBe('Finanzas & Contabilidad')
+        ->and(TaxReport::getNavigationLabel())->toBe('Declaración de Impuestos');
+});
+
+test('tax report correctly calculates monthly sales base, vat, and expenses', function () {
+    $user = User::factory()->create();
+    $customer = Customer::create([
+        'name' => 'Cliente Corporativo CA',
+        'document_type' => 'J',
+        'document_number' => '123456789',
+        'tax_category' => 'general',
+    ]);
+    $provider = Provider::create([
+        'name' => 'Proveedor Industrial CA',
+        'rif' => 'J-98765432-1',
+    ]);
+
+    // August 2026 sales
+    Sale::create([
+        'user_id' => $user->id,
+        'customer_id' => $customer->id,
+        'invoice_number' => 'FACT-001',
+        'invoice_date' => '2026-08-10',
+        'total_base' => 1000.00,
+        'total_vat' => 160.00,
+        'total_igtf' => 0.00,
+        'total_amount' => 1160.00,
+        'status' => 'paid',
+    ]);
+
+    Sale::create([
+        'user_id' => $user->id,
+        'customer_id' => $customer->id,
+        'invoice_number' => 'FACT-002',
+        'invoice_date' => '2026-08-15',
+        'total_base' => 500.00,
+        'total_vat' => 80.00,
+        'total_igtf' => 0.00,
+        'total_amount' => 580.00,
+        'status' => 'paid',
+    ]);
+
+    // August 2026 expenses
+    Expense::create([
+        'user_id' => $user->id,
+        'provider_id' => $provider->id,
+        'invoice_number' => 'GASTO-001',
+        'control_number' => 'CTRL-001',
+        'description' => 'Servicios de Internet',
+        'expense_date' => '2026-08-05',
+        'total_base' => 200.00,
+        'total_vat' => 32.00,
+        'total_amount' => 232.00,
+    ]);
+
+    // July 2026 sale (different month - should not be in August totals)
+    Sale::create([
+        'user_id' => $user->id,
+        'customer_id' => $customer->id,
+        'invoice_number' => 'FACT-JULY',
+        'invoice_date' => '2026-07-20',
+        'total_base' => 3000.00,
+        'total_vat' => 480.00,
+        'total_igtf' => 0.00,
+        'total_amount' => 3480.00,
+        'status' => 'paid',
+    ]);
+
+    $this->actingAs($user);
+
+    Livewire::test(TaxReport::class)
+        ->set('selectedMonth', 8)
+        ->set('selectedYear', 2026)
+        ->assertSet('totalSalesBase', 1500.00)
+        ->assertSet('totalSalesVat', 240.00)
+        ->assertSet('totalSalesAmount', 1740.00)
+        ->assertSet('totalExpenses', 232.00)
+        ->assertSet('netTaxToDeclare', 208.00)
+        ->assertSet('netBalance', 1268.00)
+        ->assertSet('salesCount', 2)
+        ->assertSet('expensesCount', 1)
+        ->assertSee('FACT-001')
+        ->assertSee('FACT-002')
+        ->assertSee('GASTO-001');
+});
+
+test('tax report updates totals when month or year changes', function () {
+    $user = User::factory()->create();
+    $customer = Customer::create([
+        'name' => 'Cliente Test',
+        'document_type' => 'V',
+        'document_number' => '11223344',
+        'tax_category' => 'general',
+    ]);
+
+    Sale::create([
+        'user_id' => $user->id,
+        'customer_id' => $customer->id,
+        'invoice_number' => 'FACT-MAY-01',
+        'invoice_date' => '2026-05-12',
+        'total_base' => 800.00,
+        'total_vat' => 128.00,
+        'total_igtf' => 0.00,
+        'total_amount' => 928.00,
+        'status' => 'paid',
+    ]);
+
+    $this->actingAs($user);
+
+    Livewire::test(TaxReport::class)
+        ->set('selectedMonth', 8)
+        ->set('selectedYear', 2026)
+        ->assertSet('totalSalesBase', 0.0)
+        ->assertSet('totalSalesVat', 0.0)
+        ->assertSet('salesCount', 0)
+        ->set('selectedMonth', 5)
+        ->assertSet('totalSalesBase', 800.00)
+        ->assertSet('totalSalesVat', 128.00)
+        ->assertSet('salesCount', 1)
+        ->assertSet('netTaxToDeclare', 128.00);
+});
