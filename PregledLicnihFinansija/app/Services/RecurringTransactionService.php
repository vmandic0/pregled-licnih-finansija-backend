<?php

namespace App\Services;

use App\Models\PonavljajucaTransakcija;
use App\Models\Transakcija;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecurringTransactionService
{
    public function reservedAmount(int $klijentId, CarbonImmutable $from, CarbonImmutable $through): float
    {
        $total = 0.0;

        PonavljajucaTransakcija::where('klijent_id', $klijentId)
            ->where('aktivna', true)
            ->whereHas('kategorija', fn ($query) => $query->where('tip', 'trosak'))
            ->each(function (PonavljajucaTransakcija $ponavljajuca) use ($from, $through, &$total): void {
                $nextDate = CarbonImmutable::parse($ponavljajuca->sledeci_datum);

                while ($nextDate->lessThan($from)) {
                    $nextDate = $this->nextDate(
                        $nextDate,
                        $ponavljajuca->ucestalost,
                        (int) $ponavljajuca->interval_vrednost,
                    );
                }

                while ($nextDate->lessThanOrEqualTo($through)) {
                    $total += round((float) $ponavljajuca->kolicina, 2);
                    $nextDate = $this->nextDate(
                        $nextDate,
                        $ponavljajuca->ucestalost,
                        (int) $ponavljajuca->interval_vrednost,
                    );
                }
            });

        return round($total, 2);
    }

    public function generateDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        PonavljajucaTransakcija::where('aktivna', true)
            ->whereDate('sledeci_datum', '<=', $today)
            ->each(function (PonavljajucaTransakcija $ponavljajuca) use ($today, &$created): void {
                DB::transaction(function () use ($ponavljajuca, $today, &$created): void {
                    $nextDate = CarbonImmutable::parse($ponavljajuca->sledeci_datum);

                    while ($nextDate->lessThanOrEqualTo($today)) {
                        Transakcija::create([
                            'klijent_id' => $ponavljajuca->klijent_id,
                            'kategorija_id' => $ponavljajuca->kategorija_id,
                            'kolicina' => round((float) $ponavljajuca->kolicina, 2),
                            'datum' => $nextDate,
                            'valuta' => 'RSD',
                        ]);
                        $created++;
                        $nextDate = $this->nextDate(
                            $nextDate,
                            $ponavljajuca->ucestalost,
                            (int) $ponavljajuca->interval_vrednost,
                        );
                    }

                    $ponavljajuca->update(['sledeci_datum' => $nextDate]);
                });
            });

        return $created;
    }

    private function nextDate(CarbonImmutable $date, string $frequency, int $interval): CarbonImmutable
    {
        return match ($frequency) {
            'nedeljno' => $date->addWeeks(max(1, $interval)),
            'mesecno' => $date->addMonthsNoOverflow(max(1, $interval)),
            'godisnje' => $date->addYearsNoOverflow(max(1, $interval)),
        };
    }
}
