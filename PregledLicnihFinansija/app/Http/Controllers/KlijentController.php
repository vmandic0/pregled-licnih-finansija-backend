<?php

namespace App\Http\Controllers;

use App\Models\Klijent;
use App\Models\User;
use App\Services\RecurringTransactionService;
use App\Services\SubscriptionTransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class KlijentController extends Controller
{
    // Pregled net worth-a - samo premium
    public function netWorth(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json([
                'message' => 'Korisnik nije klijent.'
            ], 403);
        }

        return response()->json([
            'net_worth' => $klijent->getNetWorth(),
        ]);
    }

    public function reservedFunds(
        Request $request,
        RecurringTransactionService $recurringTransactions,
        SubscriptionTransactionService $subscriptions,
    ) {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (! $klijent) {
            return response()->json([
                'message' => 'Korisnik nije klijent.'
            ], 403);
        }

        $today = CarbonImmutable::today();
        $from = $today->startOfMonth();
        $through = $today->endOfMonth();
        $currency = 'RSD';

        $recurringAmount = $recurringTransactions->reservedAmount($klijent->id, $from, $through);
        $subscriptionAmount = $subscriptions->reservedAmount(
            $klijent->id,
            $currency,
            $from,
            $through,
        );

        return response()->json([
            'rezervisano' => round($recurringAmount + $subscriptionAmount, 2),
            'valuta' => $currency,
            'period' => [
                'od' => $from->toDateString(),
                'do' => $through->toDateString(),
            ],
            'razrada' => [
                'ponavljajuce_transakcije' => $recurringAmount,
                'pretplate' => $subscriptionAmount,
            ],
        ]);
    }

    // Pregled profila klijenta
    public function profil(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->with('user')->first();

        if (!$klijent) {
            return response()->json([
                'message' => 'Korisnik nije klijent.'
            ], 403);
        }

        return response()->json($klijent);
    }
}