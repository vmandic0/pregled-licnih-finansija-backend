<?php

namespace App\Services;

use App\Models\Subskripcija;
use App\Models\Transakcija;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SubscriptionTransactionService
{
    public function generateDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        Subskripcija::where('aktivna', true)
            ->whereDate('sledeci_datum', '<=', $today)
            ->with('klijent')
            ->each(function (Subskripcija $sub) use ($today, &$created): void {
                DB::transaction(function () use ($sub, $today, &$created): void {
                    $nextDate = $sub->sledeci_datum;

                    while ($nextDate->lessThanOrEqualTo($today)) {
                        $preferredCurrency = $sub->klijent->preferred_currency ?: 'RSD';
                        $amount = $this->convert((float) $sub->iznos, $sub->valuta, $preferredCurrency);

                        Transakcija::create([
                            'klijent_id' => $sub->klijent_id,
                            'kategorija_id' => $sub->kategorija_id,
                            'kolicina' => $amount,
                            'datum' => $nextDate,
                            'valuta' => $preferredCurrency,
                        ]);
                        $created++;
                        $nextDate = $sub->ucestalost === 'godisnje'
                            ? $nextDate->addYearsNoOverflow()->day((int) $sub->dan_naplate)
                            : $nextDate->addMonthNoOverflow()->day((int) $sub->dan_naplate);
                    }

                    $sub->update(['sledeci_datum' => $nextDate]);
                });
            });

        return $created;
    }

    private function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return round($amount, 2);
        }
        $apiKey = env('EXCHANGE_RATE_API_KEY');
        $response = Http::get("https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$from}/{$to}");
        if (!$response->successful() || $response->json('result') !== 'success') {
            return round($amount, 2);
        }
        return round($amount * (float) $response->json('conversion_rate'), 2);
    }
}
