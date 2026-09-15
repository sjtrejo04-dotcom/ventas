<?php

namespace App\Filament\Admin\Resources\AccountingAccounts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AccountingAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
                TextInput::make('nature')
                    ->required(),
                Toggle::make('is_auxiliary')
                    ->required(),
            ]);
    }
}
