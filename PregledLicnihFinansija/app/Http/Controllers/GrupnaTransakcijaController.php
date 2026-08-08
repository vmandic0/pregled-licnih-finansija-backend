<?php

namespace App\Http\Controllers;

use App\Models\GrupnaTransakcija;
use App\Models\Klijent;
use App\Models\UdeoUGrupnojTransakciji;
use App\Models\User;
use Illuminate\Http\Request;

class GrupnaTransakcijaController extends Controller
{
    // Use case 10 - Kreiranje grupe sa članovima
    public function store(Request $request)
    {
        $request->validate([
            'naziv' => 'required|string|max:255',
            'ciljIznos' => 'required|numeric|min:0',
            'clanovi' => 'array',
            'clanovi.*' => 'exists:klijenti,id',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        // Proveri da li su svi clanovi premium
        if ($request->has('clanovi')) {
            $nijePremium = Klijent::whereIn('id', $request->clanovi)
                ->where('premium_klijent', false)
                ->exists();

            if ($nijePremium) {
                return response()->json([
                    'message' => 'Svi clanovi moraju biti premium korisnici.'
                ], 403);
            }
        }

        $grupnaTransakcija = GrupnaTransakcija::create([
            'kreator_id' => $klijent->id,
            'naziv' => $request->naziv,
            'ciljIznos' => $request->ciljIznos,
            'trenutnoPrikupljeno' => 0,
        ]);

        // Dodaj kreatora kao clana automatski
        $clanovi = $request->clanovi ?? [];
        if (!in_array($klijent->id, $clanovi)) {
            $clanovi[] = $klijent->id;
        }

        foreach ($clanovi as $clanId) {
            UdeoUGrupnojTransakciji::create([
                'grupna_transakcija_id' => $grupnaTransakcija->id,
                'klijent_id' => $clanId,
                'iznosUdela' => 0,
                'datumUplate' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Grupa uspešno kreirana!',
            'grupa' => $grupnaTransakcija->load('udeli.klijent.user'),
        ], 201);
    }

    // Pregled svih grupa u kojima klijent učestvuje ili je kreator
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $grupe = GrupnaTransakcija::where('kreator_id', $klijent->id)
            ->orWhereHas('udeli', fn($q) => $q->where('klijent_id', $klijent->id))
            ->with('udeli.klijent.user')
            ->get()
            ->map(function($grupa) {
                $grupa->procenat_prikupljeno = $grupa->proveriStanje();
                return $grupa;
            });

        return response()->json($grupe);
    }

    // Pregled jedne grupe
    public function show(Request $request, $id)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $grupa = GrupnaTransakcija::with('udeli.klijent.user')->findOrFail($id);

        // Proveri da li je korisnik kreator ili clan grupe
        $jeKreator = $grupa->kreator_id === $klijent->id;
        $jeClan = $grupa->udeli->contains('klijent_id', $klijent->id);

        if (!$jeKreator && !$jeClan) {
            return response()->json(['message' => 'Nemate pristup ovoj grupi.'], 403);
        }

        $grupa->procenat_prikupljeno = $grupa->proveriStanje();

        return response()->json($grupa);
    }

    // Use case 10 - Uplata udela
    public function uplatiUdeo(Request $request, $id)
    {
        $request->validate([
            'iznosUdela' => 'required|numeric|min:0',
            'datumUplate' => 'required|date',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $grupa = GrupnaTransakcija::findOrFail($id);

        // Proveri da li je klijent clan grupe
        $jeClan = UdeoUGrupnojTransakciji::where('grupna_transakcija_id', $id)
            ->where('klijent_id', $klijent->id)
            ->exists();

        if (!$jeClan) {
            return response()->json(['message' => 'Niste clan ove grupe.'], 403);
        }

        $udeo = UdeoUGrupnojTransakciji::create([
            'grupna_transakcija_id' => $grupa->id,
            'klijent_id' => $klijent->id,
            'iznosUdela' => $request->iznosUdela,
            'datumUplate' => $request->datumUplate,
        ]);

        $grupa->trenutnoPrikupljeno += $request->iznosUdela;
        $grupa->save();

        return response()->json([
            'message' => 'Udeo uspešno uplacen!',
            'udeo' => $udeo,
            'trenutnoPrikupljeno' => $grupa->trenutnoPrikupljeno,
            'procenat_prikupljeno' => $grupa->proveriStanje(),
        ], 201);
    }

    // Dodavanje clana u grupu - samo kreator moze
    public function dodajClana(Request $request, $id)
    {
        $request->validate([
            'klijent_id' => 'required|exists:klijenti,id',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        $grupa = GrupnaTransakcija::where('id', $id)
            ->where('kreator_id', $klijent->id)
            ->first();

        if (!$grupa) {
            return response()->json(['message' => 'Nemate pristup ovoj grupi.'], 403);
        }

        // Proveri da li je novi clan premium
        $noviClan = Klijent::find($request->klijent_id);

        if (!$noviClan->isPremium()) {
            return response()->json([
                'message' => 'Samo premium korisnici mogu biti clanovi grupe.'
            ], 403);
        }

        // Proveri da li je vec clan
        $vecClan = UdeoUGrupnojTransakciji::where('grupna_transakcija_id', $id)
            ->where('klijent_id', $request->klijent_id)
            ->exists();

        if ($vecClan) {
            return response()->json(['message' => 'Korisnik je vec clan grupe.'], 400);
        }

        UdeoUGrupnojTransakciji::create([
            'grupna_transakcija_id' => $grupa->id,
            'klijent_id' => $request->klijent_id,
            'iznosUdela' => 0,
            'datumUplate' => now(),
        ]);

        return response()->json([
            'message' => 'Clan uspešno dodat!',
            'grupa' => $grupa->load('udeli.klijent.user'),
        ]);
    }

    // Brisanje grupe - samo kreator moze
    public function destroy(Request $request, $id)
    {
        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        $grupa = GrupnaTransakcija::where('id', $id)
            ->where('kreator_id', $klijent->id)
            ->first();

        if (!$grupa) {
            return response()->json(['message' => 'Grupa nije pronadjena ili nemate pristup.'], 404);
        }

        $grupa->delete();

        return response()->json(['message' => 'Grupa uspešno obrisana.']);
    }
}