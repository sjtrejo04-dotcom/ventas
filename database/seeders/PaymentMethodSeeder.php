<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'Efectivo (Bs)', 'requires_reference' => false, 'applies_igtf' => false],
            ['name' => 'Efectivo Divisas ($)', 'requires_reference' => false, 'applies_igtf' => true],
            ['name' => 'Pago Móvil', 'requires_reference' => true, 'applies_igtf' => false],
            ['name' => 'Transferencia Bancaria', 'requires_reference' => true, 'applies_igtf' => false],
            ['name' => 'Zelle', 'requires_reference' => true, 'applies_igtf' => false],
            ['name' => 'Punto de Venta', 'requires_reference' => true, 'applies_igtf' => false],
            ['name' => 'Cashea', 'requires_reference' => true, 'applies_igtf' => false],
            ['name' => 'Tarjeta de Débito', 'requires_reference' => true, 'applies_igtf' => false],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(['name' => $method['name']], $method);
        }
    }
}
