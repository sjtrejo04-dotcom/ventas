<?php

declare(strict_types=1);

use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can view payment methods index and create page', function () {
    $user = User::factory()->create();
    $method = PaymentMethod::create([
        'name' => 'Pago Móvil Banesco',
        'requires_reference' => true,
        'applies_igtf' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.payment-methods.index'))
        ->assertSuccessful()
        ->assertSee('Pago Móvil Banesco');

    $this->actingAs($user)
        ->get(route('filament.admin.resources.payment-methods.create'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('filament.admin.resources.payment-methods.edit', ['record' => $method]))
        ->assertSuccessful();
});

test('authenticated user can view providers index and create page', function () {
    $user = User::factory()->create();
    $provider = Provider::create([
        'name' => 'Distribuidora Central C.A.',
        'rif' => 'J-12345678-0',
        'phone' => '04121234567',
        'address' => 'Caracas, Venezuela',
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.providers.index'))
        ->assertSuccessful()
        ->assertSee('Distribuidora Central C.A.')
        ->assertSee('J-12345678-0');

    $this->actingAs($user)
        ->get(route('filament.admin.resources.providers.create'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('filament.admin.resources.providers.edit', ['record' => $provider]))
        ->assertSuccessful();
});

test('authenticated user can view exchange rates index and create page', function () {
    $user = User::factory()->create();
    $rate = ExchangeRate::create([
        'currency' => 'USD',
        'rate' => 36.500000,
        'date_published' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.exchange-rates.index'))
        ->assertSuccessful()
        ->assertSee('USD');

    $this->actingAs($user)
        ->get(route('filament.admin.resources.exchange-rates.create'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('filament.admin.resources.exchange-rates.edit', ['record' => $rate]))
        ->assertSuccessful();
});
