<?php

namespace App\Filament\Admin\Resources\AccountingAccounts\Pages;

use App\Filament\Admin\Resources\AccountingAccounts\AccountingAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountingAccount extends CreateRecord
{
    protected static string $resource = AccountingAccountResource::class;
}
