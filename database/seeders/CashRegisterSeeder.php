<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CashRegister;
use Illuminate\Database\Seeder;

class CashRegisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CashRegister::firstOrCreate(
            ['code' => 'CAJA-01'],
            [
                'name' => 'Caja 01 Mostrador',
                'is_active' => true,
            ]
        );
    }
}
