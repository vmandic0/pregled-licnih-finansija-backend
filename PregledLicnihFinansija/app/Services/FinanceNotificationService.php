<?php

namespace App\Services;

use App\Models\Klijent;
use App\Models\Limit;
use App\Models\PonavljajucaTransakcija;
use App\Models\Transakcija;
use App\Models\User;
use App\Notifications\FinanceNotification;
use Carbon\CarbonImmutable;

class FinanceNotificationService
{
    public function createPending(Klijent $klijent): void
    {
        $user = $klijent->user;
        $this->createRecurringReminders($user, $klijent);
        $this->createLimitReminders($user, $klijent);
    }

    private function createRecurringReminders(User $user, Klijent $klijent): void
    {
        $tomorrow = CarbonImmutable::tomorrow()->toDateString();

        PonavljajucaTransakcija::where('klijent_id', $klijent->id)
            ->where('aktivna', true)
            ->whereDate('sledeci_datum', $tomorrow)
            ->with('kategorija')
            ->get()
            ->each(function (PonavljajucaTransakcija $transaction) use ($user, $tomorrow): void {
                $eventKey = "recurring:{$transaction->id}:{$tomorrow}";
                $this->sendOnce(
                    $user,
                    $eventKey,
                    'Ponavljajuća transakcija',
                    "Sutra se izvršava transakcija za {$transaction->kategorija->naziv}.",
                    [
                        'category' => $transaction->kategorija->naziv,
                        'amount' => $transaction->kolicina,
                        'date' => $tomorrow,
                    ],
                );
            });
    }

    private function createLimitReminders(User $user, Klijent $klijent): void
    {
        Limit::where('user_id', $user->id)
            ->with('kategorija')
            ->get()
            ->each(function (Limit $limit) use ($user, $klijent): void {
                $spent = (float) Transakcija::where('klijent_id', $klijent->id)
                    ->where('kategorija_id', $limit->kategorija_id)
                    ->sum('kolicina');
                $percentage = $limit->iznos > 0 ? ($spent / $limit->iznos) * 100 : 0;
                $level = $percentage >= 100 ? 'exceeded' : ($percentage >= 80 ? 'warning' : null);

                if (!$level) {
                    return;
                }

                $eventKey = "limit:{$limit->id}:{$level}";
                $title = $level === 'exceeded' ? 'Limit je prekoračen' : 'Približavate se limitu';
                $message = $level === 'exceeded'
                    ? "Prekoračili ste limit za {$limit->kategorija->naziv}."
                    : "Potrošili ste najmanje 80% limita za {$limit->kategorija->naziv}.";

                $this->sendOnce($user, $eventKey, $title, $message, [
                    'category' => $limit->kategorija->naziv,
                    'spent' => $spent,
                    'limit' => (float) $limit->iznos,
                    'percentage' => round($percentage, 2),
                ]);
            });
    }

    private function sendOnce(
        User $user,
        string $eventKey,
        string $title,
        string $message,
        array $details,
    ): void {
        $exists = $user->notifications()
            ->where('data->event_key', $eventKey)
            ->exists();

        if (!$exists) {
            $user->notify(new FinanceNotification($eventKey, $title, $message, $details));
        }
    }
}
