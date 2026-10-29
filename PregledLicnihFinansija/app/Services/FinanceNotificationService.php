<?php

namespace App\Services;

use App\Models\Klijent;
use App\Models\GrupnaTransakcijaPoziv;
use App\Models\Limit;
use App\Models\PonavljajucaTransakcija;
use App\Models\Transakcija;
use App\Models\User;
use App\Notifications\FinanceNotification;
use Carbon\CarbonImmutable;

class FinanceNotificationService
{
    public function sendUnique(
        User $user,
        string $eventKey,
        string $title,
        string $message,
        array $details,
    ): void {
        $this->sendOnce($user, $eventKey, $title, $message, $details);
    }

    public function createPending(Klijent $klijent): void
    {
        $user = $klijent->user;
        $this->createRecurringReminders($user, $klijent);
        $this->createLimitReminders($user, $klijent);
        $this->createGroupInvitationNotifications($user, $klijent);
    }

    private function createGroupInvitationNotifications(User $user, Klijent $klijent): void
    {
        GrupnaTransakcijaPoziv::where('pozvani_id', $klijent->id)
            ->where('status', 'na_cekanju')
            ->where(function ($query): void {
                $query->whereNull('istice_at')->orWhere('istice_at', '>', now());
            })
            ->with(['grupa', 'pozivalac.user'])
            ->get()
            ->each(function (GrupnaTransakcijaPoziv $poziv) use ($user): void {
                $this->sendOnce(
                    $user,
                    "group-invitation:{$poziv->id}",
                    'Poziv za grupnu štednju',
                    "{$poziv->pozivalac->user->name} vas je pozvao u grupu „{$poziv->grupa->naziv}“.",
                    [
                        'type' => 'group_invitation',
                        'invitation_id' => $poziv->id,
                        'group_id' => $poziv->grupna_transakcija_id,
                        'group_name' => $poziv->grupa->naziv,
                        'inviter_name' => $poziv->pozivalac->user->name,
                        'actions' => true,
                    ],
                );
            });
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
        $notifications = $user->notifications()->get(['data']);
        $exists = $notifications->contains(function ($notification) use ($eventKey): bool {
            $data = is_array($notification->data)
                ? $notification->data
                : json_decode((string) $notification->data, true);

            return is_array($data) && ($data['event_key'] ?? null) === $eventKey;
        });

        if (!$exists) {
            $user->notify(new FinanceNotification($eventKey, $title, $message, $details));
        }
    }
}
