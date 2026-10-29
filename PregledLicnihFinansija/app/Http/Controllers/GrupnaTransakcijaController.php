<?php

namespace App\Http\Controllers;

use App\Models\GrupnaTransakcija;
use App\Models\GrupnaTransakcijaPoziv;
use App\Models\Klijent;
use App\Models\UdeoUGrupnojTransakciji;
use App\Models\User;
use App\Services\FinanceNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrupnaTransakcijaController extends Controller
{
    // Use case 10 - Kreiranje grupe sa članovima
    public function store(Request $request)
    {
        $request->validate([
            'naziv' => 'required|string|max:255',
            'ciljIznos' => 'required|numeric|min:0',
            'rok' => 'nullable|date|after_or_equal:today',
            'clanovi' => 'array',
            'clanovi.*' => 'exists:klijenti,id',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        // Proveri da li su svi pozvani clanovi premium.
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

        $clanovi = collect($request->input('clanovi', []))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->reject(fn (int $id): bool => $id === $klijent->id)
            ->values()
            ->all();

        try {
            [$grupnaTransakcija, $pozivi] = DB::transaction(function () use ($request, $klijent, $clanovi) {
                $grupa = GrupnaTransakcija::create([
                    'kreator_id' => $klijent->id,
                    'naziv' => $request->naziv,
                    'ciljIznos' => $request->ciljIznos,
                    'trenutnoPrikupljeno' => 0,
                    'rok' => $request->rok,
                ]);

                UdeoUGrupnojTransakciji::create([
                    'grupna_transakcija_id' => $grupa->id,
                    'klijent_id' => $klijent->id,
                    'iznosUdela' => 0,
                    'datumUplate' => now(),
                ]);

                $pozivi = $this->createInvitations($grupa, $klijent, $clanovi);

                return [$grupa, $pozivi];
            });
        } catch (Throwable $exception) {
            Log::error('Kreiranje grupne transakcije nije uspelo.', [
                'user_id' => $user->id,
                'clanovi' => $clanovi,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'Grupa nije kreirana jer poziv nije mogao biti upisan.',
            ], 500);
        }

        $this->sendInvitationNotifications($pozivi, $klijent, $grupnaTransakcija);

        return response()->json([
            'message' => 'Grupa uspešno kreirana!',
            'grupa' => $grupnaTransakcija->load(['udeli.klijent.user', 'pozivi.pozvani.user']),
            'poslato_poziva' => count($pozivi),
        ], 201);
    }

    public function dostupniKorisnici(Request $request)
    {
        $klijent = Klijent::where('user_id', $request->user()->id)->firstOrFail();
        $grupaId = $request->integer('grupa_id');
        $search = trim((string) $request->input('pretraga', ''));

        $alreadyInGroup = $grupaId
            ? UdeoUGrupnojTransakciji::where('grupna_transakcija_id', $grupaId)->pluck('klijent_id')
            : collect();

        $users = Klijent::query()
            ->with('user:id,name,email')
            ->where('id', '!=', $klijent->id)
            ->where('premium_klijent', true)
            ->when($alreadyInGroup->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $alreadyInGroup))
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($userQuery) => $userQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->limit(20)
            ->get()
            ->map(fn (Klijent $candidate) => [
                'id' => $candidate->id,
                'name' => $candidate->user->name,
                'email' => $candidate->user->email,
            ]);

        return response()->json($users);
    }

    public function pozivi(Request $request)
    {
        $klijent = Klijent::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(
            GrupnaTransakcijaPoziv::where('pozvani_id', $klijent->id)
                ->where('status', 'na_cekanju')
                ->where(function ($query): void {
                    $query->whereNull('istice_at')->orWhere('istice_at', '>', now());
                })
                ->with(['grupa', 'pozivalac.user'])
                ->latest()
                ->get()
                ->map(fn (GrupnaTransakcijaPoziv $poziv) => [
                    'id' => $poziv->id,
                    'grupa_id' => $poziv->grupna_transakcija_id,
                    'grupa_naziv' => $poziv->grupa->naziv,
                    'pozivalac' => $poziv->pozivalac->user->name,
                    'istice_at' => $poziv->istice_at,
                ]),
        );
    }

    private function createInvitations(GrupnaTransakcija $grupa, Klijent $pozivalac, array $clanovi): array
    {
        $pozivi = [];

        foreach ($clanovi as $clanId) {
            $pozvani = Klijent::with('user')->find($clanId);
            if (!$pozvani || !$pozvani->isPremium()) {
                Log::warning('Poziv za grupnu transakciju je preskočen.', [
                    'group_id' => $grupa->id,
                    'recipient_client_id' => $clanId,
                    'reason' => !$pozvani ? 'client_not_found' : 'client_not_premium',
                ]);
                continue;
            }

            $pozivi[] = GrupnaTransakcijaPoziv::firstOrCreate(
                [
                    'grupna_transakcija_id' => $grupa->id,
                    'pozvani_id' => $pozvani->id,
                ],
                [
                    'pozivalac_id' => $pozivalac->id,
                    'status' => 'na_cekanju',
                    'istice_at' => Carbon::now()->addDays(7),
                ],
            );
        }

        return $pozivi;
    }

    private function sendInvitationNotifications(array $pozivi, Klijent $pozivalac, GrupnaTransakcija $grupa): void
    {
        foreach ($pozivi as $poziv) {
            try {
                $pozvani = $poziv->pozvani()->with('user')->firstOrFail();
                app(FinanceNotificationService::class)->sendUnique(
                    $pozvani->user,
                    "group-invitation:{$poziv->id}",
                    'Poziv za grupnu štednju',
                    "{$pozivalac->user->name} vas je pozvao u grupu „{$grupa->naziv}“.",
                    [
                        'type' => 'group_invitation',
                        'invitation_id' => $poziv->id,
                        'group_id' => $grupa->id,
                        'group_name' => $grupa->naziv,
                        'inviter_name' => $pozivalac->user->name,
                        'actions' => true,
                    ],
                );
            } catch (Throwable $exception) {
                Log::error('Slanje poziva za grupnu transakciju nije uspelo.', [
                    'invitation_id' => $poziv->id,
                    'recipient_client_id' => $poziv->pozvani_id,
                    'exception' => $exception,
                ]);
                error_log('Slanje poziva za grupnu transakciju nije uspelo: '.$exception->getMessage());
            }
        }
    }

    public function odgovorNaPoziv(Request $request, int $id, string $odgovor)
    {
        if (!in_array($odgovor, ['prihvati', 'odbij'], true)) {
            return response()->json(['message' => 'Nevažeći odgovor.'], 422);
        }

        $klijent = Klijent::where('user_id', $request->user()->id)->firstOrFail();
        $poziv = GrupnaTransakcijaPoziv::with(['grupa', 'pozivalac.user'])
            ->where('id', $id)
            ->where('pozvani_id', $klijent->id)
            ->where('status', 'na_cekanju')
            ->first();

        if (!$poziv) {
            return response()->json(['message' => 'Poziv nije pronađen ili je već obrađen.'], 404);
        }

        if ($poziv->istice_at?->isPast()) {
            $poziv->update(['status' => 'istekao', 'odgovoreno_at' => now()]);
            return response()->json(['message' => 'Poziv je istekao.'], 422);
        }

        $accepted = $odgovor === 'prihvati';
        $poziv->update([
            'status' => $accepted ? 'prihvacen' : 'odbijen',
            'odgovoreno_at' => now(),
        ]);

        if ($accepted) {
            UdeoUGrupnojTransakciji::firstOrCreate(
                ['grupna_transakcija_id' => $poziv->grupna_transakcija_id, 'klijent_id' => $klijent->id],
                ['iznosUdela' => 0, 'datumUplate' => now()],
            );
        }

        $statusText = $accepted ? 'prihvatio' : 'odbio';
        app(FinanceNotificationService::class)->sendUnique(
            $poziv->pozivalac->user,
            "group-invitation-response:{$poziv->id}:{$poziv->status}",
            'Odgovor na poziv za grupu',
            "{$klijent->user->name} je {$statusText} poziv za grupu „{$poziv->grupa->naziv}“.",
            [
                'type' => 'group_invitation_response',
                'invitation_id' => $poziv->id,
                'group_id' => $poziv->grupna_transakcija_id,
                'group_name' => $poziv->grupa->naziv,
                'member_name' => $klijent->user->name,
                'status' => $poziv->status,
            ],
        );

        return response()->json(['message' => $accepted ? 'Poziv prihvaćen.' : 'Poziv odbijen.']);
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
            ->with(['udeli.klijent.user', 'pozivi.pozvani.user'])
            ->get()
            ->map(function($grupa) {
                $grupa->procenat_prikupljeno = $grupa->proveriStanje();
                $grupa->je_kreator = $grupa->kreator_id === request()->user()->klijent?->id;
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

        $grupa = GrupnaTransakcija::with(['udeli.klijent.user', 'pozivi.pozvani.user'])->findOrFail($id);

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

        $this->createInvitations($grupa, $klijent, [$request->klijent_id]);

        return response()->json([
            'message' => 'Poziv za člana je poslat!',
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