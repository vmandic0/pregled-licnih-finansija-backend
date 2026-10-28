<?php

namespace App\Http\Controllers;

use App\Models\Kategorija;
use App\Models\Klijent;
use App\Models\Limit;
use App\Models\Transakcija;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Http\Request;

class TransakcijaController extends Controller
{
    
    // Use case 3 - Unos transakcije
    
    public function store(Request $request)
    {
    $request->validate([
        'kategorija_id' => 'required|exists:kategorije,id',
        'kolicina' => 'required|numeric',
        'datum' => 'required|date',
    ]);

    /** @var User $user */
    $user = $request->user();
    $klijent = Klijent::where('user_id', $user->id)->first();

    if (!$klijent) {
        return response()->json([
            'message' => 'Korisnik nije klijent.'
        ], 403);
    }

    $transakcija = Transakcija::create([
        'klijent_id' => $klijent->id,
        'kategorija_id' => $request->kategorija_id,
        'kolicina' => $request->kolicina,
        'datum' => $request->datum,
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

        return response()->json($query->orderBy('datum', 'desc')->get());
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