<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Expenses\Pages;

use App\Filament\Admin\Resources\Expenses\ExpenseResource;
use App\Services\CommercialCalculationService;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = $data['user_id'] ?? auth()->id();
        $data['expense_date'] = $data['expense_date'] ?? $data['invoice_date'] ?? now()->toDateString();

        if (isset($data['items']) && is_array($data['items'])) {
            $totals = CommercialCalculationService::calculateInvoiceTotals($data['items']);
            $data['total_base'] = $totals['total_base'];
            $data['total_exempt'] = $totals['total_exempt'];
            $data['total_vat'] = $totals['total_vat'];
            $data['total_amount'] = $totals['total_amount'];
        }

        return $data;
    }
}
