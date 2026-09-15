<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class TaxReport extends Page implements HasActions, HasInfolists
{
    use InteractsWithActions;
    use InteractsWithInfolists;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Declaración de Impuestos';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.admin.pages.tax-report';

    public int $selectedMonth = 1;

    public int $selectedYear = 2026;

    public float $totalSalesBase = 0.0;

    public float $totalSalesVat = 0.0;

    public float $totalSalesAmount = 0.0;

    public float $totalExpenses = 0.0;

    public float $totalExpensesBase = 0.0;

    public float $totalExpensesVat = 0.0;

    public float $netTaxToDeclare = 0.0;

    public float $netBalance = 0.0;

    public int $salesCount = 0;

    public int $expensesCount = 0;

    public function getTitle(): string|Htmlable
    {
        return 'Reporte de Declaración de Impuestos';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finanzas & Contabilidad';
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->selectedMonth = (int) now()->month;
        $this->selectedYear = (int) now()->year;
        $this->loadData();
    }

    public function updatedSelectedMonth(): void
    {
        $this->loadData();
    }

    public function updatedSelectedYear(): void
    {
        $this->loadData();
    }

    public function filter(): void
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth();

        $startDate = $startOfMonth->toDateString();
        $endDate = $endOfMonth->toDateString();

        $salesQuery = Sale::query()
            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
                $query->whereBetween('invoice_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->whereNull('invoice_date')
                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
                    });
            });

        $expensesQuery = Expense::query()
            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
                $query->whereBetween('expense_date', [$startDate, $endDate])
                    ->orWhereBetween('invoice_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->whereNull('expense_date')
                            ->whereNull('invoice_date')
                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
                    });
            });

        $this->salesCount = (int) $salesQuery->count();
        $this->totalSalesBase = (float) round((float) $salesQuery->sum('total_base'), 2);
        $this->totalSalesVat = (float) round((float) $salesQuery->sum('total_vat'), 2);
        $this->totalSalesAmount = (float) round((float) $salesQuery->sum('total_amount'), 2);

        $this->expensesCount = (int) $expensesQuery->count();
        $this->totalExpenses = (float) round((float) $expensesQuery->sum('total_amount'), 2);
        $this->totalExpensesBase = (float) round((float) $expensesQuery->sum('total_base'), 2);
        $this->totalExpensesVat = (float) round((float) $expensesQuery->sum('total_vat'), 2);

        // El IVA Neto a Declarar es el Débito Fiscal menos el Crédito Fiscal
        $this->netTaxToDeclare = $this->totalSalesVat - $this->totalExpensesVat;

        // Balance Operativo: Base Imponible de Ventas - Total Gastos
        $this->netBalance = (float) round($this->totalSalesBase - $this->totalExpenses, 2);
    }

    /**
     * @return array<int, string>
     */
    public function getMonths(): array
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getYears(): array
    {
        $currentYear = (int) now()->year;
        $years = [];
        for ($y = $currentYear - 4; $y <= $currentYear + 1; $y++) {
            $years[$y] = $y;
        }

        return $years;
    }

    public function viewSaleAction(): Action
    {
        return Action::make('viewSale')
            ->modalHeading('Detalle de Venta')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->record(function (array $arguments) {
                return Sale::find($arguments['sale']);
            })
            ->schema([
                TextEntry::make('invoice_number')->label('Factura'),
                TextEntry::make('invoice_date')->label('Fecha de Factura')->date(),
                TextEntry::make('created_at')->label('Fecha de Registro')->dateTime(),
                TextEntry::make('customer.name')->label('Cliente'),
                TextEntry::make('total_amount')->label('Total'),
                RepeatableEntry::make('items')
                    ->schema([
                        TextEntry::make('product.name')->label('Producto'),
                        TextEntry::make('quantity')->label('Cant.'),
                        TextEntry::make('subtotal')->label('Subtotal'),
                    ])->columns(3),
            ]);
    }

    protected function getViewData(): array
    {
        $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfMonth();
        $endOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth();
        $startDate = $startOfMonth->toDateString();
        $endDate = $endOfMonth->toDateString();

        $recentSales = Sale::with(['customer', 'user'])
            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
                $query->whereBetween('invoice_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->whereNull('invoice_date')
                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
                    });
            })
            ->latest('invoice_date')
            ->latest('id')
            ->take(15)
            ->get();

        $recentExpenses = Expense::with(['provider', 'user'])
            ->where(function ($query) use ($startDate, $endDate, $startOfMonth, $endOfMonth) {
                $query->whereBetween('expense_date', [$startDate, $endDate])
                    ->orWhereBetween('invoice_date', [$startDate, $endDate])
                    ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                        $sub->whereNull('expense_date')
                            ->whereNull('invoice_date')
                            ->whereBetween('created_at', [$startOfMonth->toDateTimeString(), $endOfMonth->toDateTimeString()]);
                    });
            })
            ->latest('expense_date')
            ->latest('id')
            ->take(15)
            ->get();

        return [
            'months' => $this->getMonths(),
            'years' => $this->getYears(),
            'periodName' => ($this->getMonths()[$this->selectedMonth] ?? '').' '.$this->selectedYear,
            'recentSales' => $recentSales,
            'recentExpenses' => $recentExpenses,
        ];
    }
}
