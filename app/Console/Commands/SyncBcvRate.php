<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncBcvRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bcv:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza la tasa oficial del BCV desde ve.dolarapi.com';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Consultando tasa del BCV...');

        try {
            // Se usa el endpoint de ve.dolarapi.com
            $response = Http::timeout(10)->get('https://ve.dolarapi.com/v1/dolares/oficial');

            if ($response->successful()) {
                $data = $response->json();

                $rate = $data['promedio'] ?? null;
                $datePublished = isset($data['fechaActualizacion'])
                    ? Carbon::parse($data['fechaActualizacion'])
                    : now();

                if ($rate) {
                    ExchangeRate::create([
                        'currency' => 'USD',
                        'rate' => $rate,
                        'date_published' => $datePublished,
                    ]);

                    $this->info("Tasa BCV actualizada: {$rate} Bs/USD");
                } else {
                    $this->error('No se pudo leer la tasa promedio del JSON devuelto.');
                }
            } else {
                $this->error('Error al consultar la API: '.$response->status());
            }
        } catch (\Exception $e) {
            $this->error('Excepción al intentar sincronizar: '.$e->getMessage());
        }
    }
}
