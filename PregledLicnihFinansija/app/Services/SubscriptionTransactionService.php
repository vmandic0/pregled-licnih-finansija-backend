<?php

namespace App\Services;

use App\Models\Subskripcija;
use App\Models\Transakcija;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SubscriptionTransactionService
{
    public function __construct(private CurrencyConversionService $currencyConversion)
    {
    }

    public function reservedAmount(
        int $klijentId,
        string $currency,
        CarbonImmutable $from,
        CarbonImmutable $through,
    ): float {
        $total = 0.0;

        Subskripcija::where('klijent_id', $klijentId)
            ->where('aktivna', true)
            ->whereHas('kategorija', fn ($query) => $query->where('tip', 'trosak'))
            ->with('klijent')
            ->each(function (Subskripcija $sub) use ($currency, $from, $through, &$total): void {
                $nextDate = CarbonImmutable::parse($sub->sledeci_datum);

                while ($nextDate->lessThan($from)) {
                    $nextDate = $this->nextDate($nextDate, $sub);
                }

                if ($nextDate->greaterThan($through)) {
                    return;
                }

                $amount = $this->currencyConversion->convert((float) $sub->iznos, $sub->valuta, $currency);

                while ($nextDate->lessThanOrEqualTo($through)) {
                    $total += $amount;
                    $nextDate = $this->nextDate($nextDate, $sub);
                }
            });

        return round($total, 2);
    }

    public function generateDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        Subskripcija::where('aktivna', true)
            ->whereDate('sledeci_datum', '<=', $today)
            ->each(function (Subskripcija $sub) use ($today, &$created): void {
                DB::transaction(function () use ($sub, $today, &$created): void {
                    $nextDate = CarbonImmutable::parse($sub->sledeci_datum);

                    while ($nextDate->lessThanOrEqualTo($today)) {
                        $this->createTransaction($sub, $nextDate);
                        $created++;
                        $nextDate = $this->nextDate($nextDate, $sub);
                    }

                    $sub->update(['sledeci_datum' => $nextDate]);
                });
            });

        return $created;
    }

    public function recordInitialCharge(Subskripcija $sub, CarbonImmutable $date): void
    {
        $this->createTransaction($sub, $date);
        $sub->update(['sledeci_datum' => $this->nextDate($date, $sub)]);
    }

    private function createTransaction(Subskripcija $sub, CarbonImmutable $date): void
    {
        $amount = $this->currencyConversion->convert((float) $sub->iznos, $sub->valuta, 'RSD');

        Transakcija::create([
            'klijent_id' => $sub->klijent_id,
            'kategorija_id' => $sub->kategorija_id,
            'kolicina' => $amount,
            'datum' => $date,
            'valuta' => 'RSD',
        ]);
    }

    private function nextDate(CarbonImmutable $date, Subskripcija $sub): CarbonImmutable
    {
        return $sub->ucestalost === 'godisnje'
            ? $date->addYearsNoOverflow()->day((int) $sub->dan_naplate)
            : $date->addMonthNoOverflow()->day((int) $sub->dan_naplate);
    }

}
