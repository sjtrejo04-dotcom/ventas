<?php

namespace App\Filament\Admin\Pages;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Sale;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class CustomDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = '';

    protected string $view = 'filament.admin.pages.custom-dashboard';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getViewData(): array
    {
        $todaySales = Sale::whereDate('created_at', today())->sum('total_amount');
        $totalSales = Sale::sum('total_amount');
        $displaySales = $todaySales;

        $todayExpenses = Expense::whereDate('created_at', today())->orWhereDate('expense_date', today())->sum('total_amount');
        $totalExpenses = Expense::sum('total_amount');
        $displayExpenses = $todayExpenses;

        $netBalance = $displaySales - $displayExpenses;

        $recentSales = Sale::with(['customer', 'payments.paymentMethod', 'user'])
            ->latest()
            ->take(6)
            ->get();

        return [
            'todaySales' => $displaySales,
            'todayExpenses' => $displayExpenses,
            'netBalance' => $netBalance,
            'recentSales' => $recentSales,
            'totalRevenue' => $totalSales,
            'totalOrders' => Sale::count(),
            'totalCustomers' => Customer::count(),
        ];
    }
}
