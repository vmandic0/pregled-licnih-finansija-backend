<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class CurrencyConversionService
{
    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        return round($amount * $this->rate($from, $to), 2);
    }

    public function rate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        return Cache::remember("exchange-rate:{$from}:{$to}", 3600, function () use ($from, $to): float {
            $apiKey = env('EXCHANGE_RATE_API_KEY');
            $response = Http::timeout(10)->get(
                "https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$from}/{$to}",
            );

            if (!$response->successful() || $response->json('result') !== 'success') {
                throw new RuntimeException('Nije moguće preuzeti kurs za konverziju valuta.');
            }

            $rate = $response->json('conversion_rate');
            if (!is_numeric($rate) || (float) $rate <= 0) {
                throw new RuntimeException('Servis za konverziju je vratio neispravan kurs.');
            }

            return (float) $rate;
        });
    }
}
