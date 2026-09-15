<?php

namespace App\Filament\Admin\Resources\AccountingAccounts;

use App\Filament\Admin\Resources\AccountingAccounts\Pages\CreateAccountingAccount;
use App\Filament\Admin\Resources\AccountingAccounts\Pages\EditAccountingAccount;
use App\Filament\Admin\Resources\AccountingAccounts\Pages\ListAccountingAccounts;
use App\Filament\Admin\Resources\AccountingAccounts\Schemas\AccountingAccountForm;
use App\Filament\Admin\Resources\AccountingAccounts\Tables\AccountingAccountsTable;
use App\Models\AccountingAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccountingAccountResource extends Resource
{
    protected static ?string $model = AccountingAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'description';

    public static function getModelLabel(): string
    {
        return 'Cuenta Contable';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Cuentas Contables';
    }

    public static function getNavigationLabel(): string
    {
        return 'Plan de Cuentas';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Finanzas & Contabilidad';
    }

    public static function form(Schema $schema): Schema
    {
        return AccountingAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountingAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingAccounts::route('/'),
            'create' => CreateAccountingAccount::route('/create'),
            'edit' => EditAccountingAccount::route('/{record}/edit'),
        ];
    }
}
