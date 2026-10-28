<?php

namespace App\Services;

use App\Models\PonavljajucaTransakcija;
use App\Models\Transakcija;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecurringTransactionService
{
    public function generateDue(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        PonavljajucaTransakcija::where('aktivna', true)
            ->whereDate('sledeci_datum', '<=', $today)
            ->each(function (PonavljajucaTransakcija $ponavljajuca) use ($today, &$created): void {
                DB::transaction(function () use ($ponavljajuca, $today, &$created): void {
                    $nextDate = $ponavljajuca->sledeci_datum;

                    while ($nextDate->lessThanOrEqualTo($today)) {
                        Transakcija::create([
                            'klijent_id' => $ponavljajuca->klijent_id,
                            'kategorija_id' => $ponavljajuca->kategorija_id,
                            'kolicina' => $ponavljajuca->kolicina,
                            'datum' => $nextDate,
                        ]);
                        $created++;
                        $nextDate = $this->nextDate($nextDate, $ponavljajuca->ucestalost);
                    }

                    $ponavljajuca->update(['sledeci_datum' => $nextDate]);
                });
            });

        return $created;
    }

    private function nextDate(CarbonImmutable $date, string $frequency): CarbonImmutable
    {
        return match ($frequency) {
            'nedeljno' => $date->addWeek(),
            'mesecno' => $date->addMonthNoOverflow(),
            'godisnje' => $date->addYearNoOverflow(),
        };
    }
}
