<?php

namespace App\Http\Controllers;

use App\Models\Kategorija;
use App\Models\Klijent;
use App\Models\Limit;
use App\Models\Transakcija;
use App\Models\User;
use App\Services\CurrencyConversionService;
use App\Services\RecurringTransactionService;
use Illuminate\Http\Request;

class TransakcijaController extends Controller
{
    
    // Use case 3 - Unos transakcije
    
    public function store(Request $request, CurrencyConversionService $currencyConversion)
    {
    $request->validate([
        'kategorija_id' => 'required|exists:kategorije,id',
        'kolicina' => 'required|numeric|decimal:0,2',
        'datum' => 'required|date',
        'request_id' => 'sometimes|nullable|string|max:64',
    ]);

    /** @var User $user */
    $user = $request->user();
    $klijent = Klijent::where('user_id', $user->id)->first();

    if (!$klijent) {
        return response()->json([
            'message' => 'Korisnik nije klijent.'
        ], 403);
    }

    $kategorija = Kategorija::whereKey($request->kategorija_id)
        ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $user->id))
        ->first();

    if (!$kategorija) {
        return response()->json([
            'message' => 'Izabrana kategorija nije dostupna.'
        ], 403);
    }

    if ($request->filled('request_id')) {
        $existing = Transakcija::where('request_id', $request->request_id)
            ->where('klijent_id', $klijent->id)
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'Transakcija je već dodata.',
                'transakcija' => $existing,
            ], 200);
        }
    }

    $transakcija = Transakcija::create([
        'klijent_id' => $klijent->id,
        'kategorija_id' => $request->kategorija_id,
        'kolicina' => $currencyConversion->convert(
            (float) $request->kolicina,
            $klijent->preferred_currency ?: 'RSD',
            'RSD',
        ),
        'datum' => $request->datum,
        'valuta' => 'RSD',
        'request_id' => $request->request_id,
    ]);

    $gejmifikacija = new \App\Http\Controllers\GejmifikacijaController();
    $gejmifikacija->proveriRedovanUnos($klijent->id);

        if ($klijent->isPremium()) {
            $klijent->azurirajNetWorth();
        }   

    $limit = Limit::where('user_id', $user->id)
        ->where('kategorija_id', $request->kategorija_id)
        ->first();

    $upozorenje = null;

    if ($limit) {
        $ukupnoPotrošeno = Transakcija::where('klijent_id', $klijent->id)
            ->where('kategorija_id', $request->kategorija_id)
            ->sum('kolicina');

        $procenat = ($ukupnoPotrošeno / $limit->iznos) * 100;

        if ($procenat >= 100) {
            $upozorenje = 'Prešli ste limit za ovu kategoriju!';
        } elseif ($procenat >= 80) {
            $upozorenje = 'Upozorenje: Potrošili ste ' . round($procenat) . '% od vašeg limita!';
        } else {
            $gejmifikacija = new \App\Http\Controllers\GejmifikacijaController();
            $gejmifikacija->dodajPoene($klijent->id, 5);
        }





    }

    return response()->json([
        'message' => 'Transakcija uspešno dodata!',
        'transakcija' => $transakcija,
        'upozorenje' => $upozorenje,
    ], 201);

    }

    // Use case 6 - Istorija aktivnosti
    public function index(Request $request, RecurringTransactionService $recurringTransactions)
    {
        $recurringTransactions->generateDue();

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json([
                'message' => 'Korisnik nije klijent.'
            ], 403);
        }

        $transakcije = Transakcija::where('klijent_id', $klijent->id)
            ->with('kategorija')
            ->orderBy('datum', 'desc')
            ->get();

        $transakcije->each(function (Transakcija $transakcija): void {
            $transakcija->valuta = 'RSD';
        });

        return response()->json($transakcije);
    }

    // Use case 6 - Filtriranje
    public function filter(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json([
                'message' => 'Korisnik nije klijent.'
            ], 403);
        }

        $query = Transakcija::where('klijent_id', $klijent->id)->with('kategorija');

        if ($request->has('kategorija_id')) {
            $query->where('kategorija_id', $request->kategorija_id);
        }

        if ($request->has('od') && $request->has('do')) {
            $query->whereBetween('datum', [$request->od, $request->do]);
        }

        $transakcije = $query->orderBy('datum', 'desc')->get();
        $transakcije->each(function (Transakcija $transakcija): void {
            $transakcija->valuta = 'RSD';
        });

        return response()->json($transakcije);
    }

    // Brisanje transakcije
    public function destroy(Request $request, $id)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        $transakcija = Transakcija::where('id', $id)
            ->where('klijent_id', $klijent->id)
            ->first();

        if (!$transakcija) {
            return response()->json([
                'message' => 'Transakcija nije pronađena.'
            ], 404);
        }

        $transakcija->delete();

        if ($klijent->isPremium()) {
            $klijent->azurirajNetWorth();
        }

        return response()->json([
            'message' => 'Transakcija uspešno obrisana.'
        ]);

        
















    }














}