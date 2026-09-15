<?php

declare(strict_types=1);

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can view inventory movements index and create page', function () {
    $user = User::factory()->create();
    $product = Product::create([
        'name' => 'Harina PAN 1kg',
        'description' => 'Harina de maíz precocida',
        'price' => 1.50,
        'cost' => 1.00,
        'stock' => 10,
        'has_vat' => true,
    ]);

    $movement = InventoryMovement::create([
        'product_id' => $product->id,
        'user_id' => $user->id,
        'type' => 'in',
        'concept' => 'Compra inicial',
        'quantity' => 20,
        'unit_cost' => 1.00,
        'stock_after_movement' => 30,
    ]);

    $this->actingAs($user)
        ->get(route('filament.admin.resources.inventory-movements.index'))
        ->assertSuccessful()
        ->assertSee('Harina PAN 1kg')
        ->assertSee('Compra inicial');

    $this->actingAs($user)
        ->get(route('filament.admin.resources.inventory-movements.create'))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('filament.admin.resources.inventory-movements.view', ['record' => $movement]))
        ->assertSuccessful();
});

test('creating an "in" inventory movement increments product stock and calculates stock_after_movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::create([
        'name' => 'Arroz Mary 1kg',
        'price' => 1.80,
        'cost' => 1.20,
        'stock' => 15,
        'has_vat' => false,
    ]);

    $movement = InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'in',
        'concept' => 'Recepcion de mercancia',
        'quantity' => 25,
    ]);

    expect((int) $product->fresh()->stock)->toBe(40)
        ->and((float) $movement->fresh()->stock_after_movement)->toBe(40.0)
        ->and((float) $movement->fresh()->unit_cost)->toBe(1.20)
        ->and($movement->fresh()->user_id)->toBe($user->id);
});

test('creating an "out" inventory movement decrements product stock and calculates stock_after_movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::create([
        'name' => 'Aceite Mazeite 1L',
        'price' => 3.50,
        'cost' => 2.50,
        'stock' => 50,
        'has_vat' => true,
    ]);

    $movement = InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'out',
        'concept' => 'Merma por producto dañado',
        'quantity' => 5,
    ]);

    expect((int) $product->fresh()->stock)->toBe(45)
        ->and((float) $movement->fresh()->stock_after_movement)->toBe(45.0)
        ->and($movement->fresh()->user_id)->toBe($user->id);
});

test('creating an "adjustment" inventory movement sets product stock directly and calculates stock_after_movement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $product = Product::create([
        'name' => 'Cafe Fama de America 500g',
        'price' => 5.00,
        'cost' => 3.50,
        'stock' => 20,
        'has_vat' => true,
    ]);

    $movement = InventoryMovement::create([
        'product_id' => $product->id,
        'type' => 'adjustment',
        'concept' => 'Ajuste de inventario fisico',
        'quantity' => 18,
    ]);

    expect((int) $product->fresh()->stock)->toBe(18)
        ->and((float) $movement->fresh()->stock_after_movement)->toBe(18.0)
        ->and($movement->fresh()->user_id)->toBe($user->id);
});
