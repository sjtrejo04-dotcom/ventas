<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\TaxReport;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Provider;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('authenticated user can view tax report page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('filament.admin.pages.tax-report'))
        ->assertSuccessful()
        ->assertSee('Declaración de Impuestos')
        ->assertSee('Total Ventas (Base)')
        ->assertSee('IVA Cobrado (Débito)')
        ->assertSee('Total Gastos (Egresos)')
        ->assertSee('Total a Declarar (IVA)');
});

test('tax report page navigation attributes are properly configured', function () {
    expect(TaxReport::getNavigationGroup())->toBe('Finanzas & Contabilidad')
        ->and(TaxReport::getNavigationLabel())->toBe('Declaración de Impuestos');
});

test('tax report correctly calculates monthly sales base, vat, and expenses', function () {
    $user = User::factory()->create();
    $customer = Customer::create([
        'name' => 'Cliente Corporativo CA',
        'document_type' => 'J',
        'document_number' => '123456789',
        'tax_category' => 'general',
    ]);
    $provider = Provider::create([
        'name' => 'Proveedor Industrial CA',
        'rif' => 'J-98765432-1',
    ]);

    // August 2026 sales
    Sale::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'FACT-001',
        'invoice_date' => '2026-08-10',
        'total_base' => 1000.00,
        'total_vat' => 160.00,
        'total_igtf' => 0.00,
        'total_amount' => 1160.00,
        'status' => 'paid',
    ]);

    Sale::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'FACT-002',
        'invoice_date' => '2026-08-15',
        'total_base' => 500.00,
        'total_vat' => 80.00,
        'total_igtf' => 0.00,
        'total_amount' => 580.00,
        'status' => 'paid',
    ]);

    // August 2026 expenses
    Expense::create([
        'user_id' => $user->id,
        'provider_id' => $provider->id,
        'invoice_number' => 'GASTO-001',
        'control_number' => 'CTRL-001',
        'description' => 'Servicios de Internet',
        'expense_date' => '2026-08-05',
        'total_base' => 200.00,
        'total_vat' => 32.00,
        'total_amount' => 232.00,
    ]);

    // July 2026 sale (different month - should not be in August totals)
    Sale::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'FACT-JULY',
        'invoice_date' => '2026-07-20',
        'total_base' => 3000.00,
        'total_vat' => 480.00,
        'total_igtf' => 0.00,
        'total_amount' => 3480.00,
        'status' => 'paid',
    ]);

    $this->actingAs($user);

    Livewire::test(TaxReport::class)
        ->set('selectedMonth', 8)
        ->set('selectedYear', 2026)
        ->assertSet('totalSalesBase', 1500.00)
        ->assertSet('totalSalesVat', 240.00)
        ->assertSet('totalSalesAmount', 1740.00)
        ->assertSet('totalExpenses', 232.00)
        ->assertSet('netTaxToDeclare', 208.00)
        ->assertSet('netBalance', 1268.00)
        ->assertSet('salesCount', 2)
        ->assertSet('expensesCount', 1)
        ->assertSee('FACT-001')
        ->assertSee('FACT-002')
        ->assertSee('GASTO-001');
});

test('tax report updates totals when month or year changes', function () {
    $user = User::factory()->create();
    $customer = Customer::create([
        'name' => 'Cliente Test',
        'document_type' => 'V',
        'document_number' => '11223344',
        'tax_category' => 'general',
    ]);

    Sale::create([
        'user_id' => $user->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'FACT-MAY-01',
        'invoice_date' => '2026-05-12',
        'total_base' => 800.00,
        'total_vat' => 128.00,
        'total_igtf' => 0.00,
        'total_amount' => 928.00,
        'status' => 'paid',
    ]);

    $this->actingAs($user);

    Livewire::test(TaxReport::class)
        ->set('selectedMonth', 8)
        ->set('selectedYear', 2026)
        ->assertSet('totalSalesBase', 0.0)
        ->assertSet('totalSalesVat', 0.0)
        ->assertSet('salesCount', 0)
        ->set('selectedMonth', 5)
        ->assertSet('totalSalesBase', 800.00)
        ->assertSet('totalSalesVat', 128.00)
        ->assertSet('salesCount', 1)
        ->assertSet('netTaxToDeclare', 128.00);
});
