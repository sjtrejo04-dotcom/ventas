<?php

namespace App\Filament\Admin\Resources\AccountingAccounts\Pages;

use App\Filament\Admin\Resources\AccountingAccounts\AccountingAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountingAccount extends EditRecord
{
    protected static string $resource = AccountingAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
