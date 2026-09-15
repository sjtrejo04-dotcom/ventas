<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\ExchangeRate;

#[Signature('app:sync-bcv-rates')]
#[Description('Sync USD and EUR exchange rates from BCV')]
class SyncBcvRates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = Http::withOptions(['verify' => false])->get('https://www.bcv.org.ve/');

        if ($response->successful()) {
            $crawler = new Crawler($response->body());

            $usdNode = $crawler->filter('#dolar strong');
            $eurNode = $crawler->filter('#euro strong');

            if ($usdNode->count() > 0 && $eurNode->count() > 0) {
                $usdRateString = $usdNode->text();
                $eurRateString = $eurNode->text();

                // Clean the strings (replace ',' with '.') and cast to float
                $usdRate = (float) str_replace(',', '.', trim($usdRateString));
                $eurRate = (float) str_replace(',', '.', trim($eurRateString));

                ExchangeRate::updateOrCreate(
                    ['currency' => 'USD', 'date_published' => now()->startOfDay()],
                    ['rate' => $usdRate]
                );

                ExchangeRate::updateOrCreate(
                    ['currency' => 'EUR', 'date_published' => now()->startOfDay()],
                    ['rate' => $eurRate]
                );

                $this->info("BCV Rates synced. USD: {$usdRate}, EUR: {$eurRate}");
            } else {
                $this->error('Failed to find rate nodes in BCV page.');
            }
        } else {
            $this->error('Failed to fetch BCV page.');
        }
    }
}
