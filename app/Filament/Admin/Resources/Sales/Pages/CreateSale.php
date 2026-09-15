<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Sales\Pages;

use App\Filament\Admin\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Resources\Pages\CreateRecord;

class CreateSale extends CreateRecord
{
    use HasUnsavedDataChangesAlert;

    protected static string $resource = SaleResource::class;

    protected ?string $heading = '';

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            ...(static::canCreateAnother() ? [$this->getCreateAnotherFormAction()] : []),
            $this->getCancelFormAction(),
            Action::make('clear')
                ->label('Limpiar Formulario')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->action(fn () => $this->form->fill())
                ->requiresConfirmation()
                ->modalHeading('¿Limpiar todos los datos?')
                ->modalDescription('Esto borrará toda la información que has ingresado. ¿Estás seguro?'),
        ];
    }

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

                // Cálculo simple de IVA
                $totalVat += $lineTotal * config('app.vat_rate', 0.16);
            }
        }

        $data['total_base'] = $totalBase;
        $data['total_vat'] = $totalVat;
        $data['total_amount'] = $totalBase + $totalVat + (float) ($data['total_igtf'] ?? 0);

        return $data;
    }
}
