<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PaymentMethods\Pages;

use App\Filament\Admin\Resources\PaymentMethods\PaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentMethod extends CreateRecord
{
    protected static string $resource = PaymentMethodResource::class;
}
