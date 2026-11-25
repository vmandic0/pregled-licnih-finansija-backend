<?php

namespace App\Http\Controllers;

use App\Models\Klijent;
use App\Models\Kategorija;
use App\Models\Subskripcija;
use App\Services\CurrencyConversionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class SubskripcijaController extends Controller
{
    public function popularne()
    {
        return response()->json([
            ['naziv' => 'Netflix', 'valuta' => 'EUR'],
            ['naziv' => 'Spotify', 'valuta' => 'EUR'],
            ['naziv' => 'YouTube Premium', 'valuta' => 'EUR'],
            ['naziv' => 'iCloud+', 'valuta' => 'EUR'],
            ['naziv' => 'Apple One', 'valuta' => 'EUR'],
            ['naziv' => 'Microsoft 365', 'valuta' => 'EUR'],
            ['naziv' => 'Adobe Creative Cloud', 'valuta' => 'EUR'],
            ['naziv' => 'Google One', 'valuta' => 'EUR'],
            ['naziv' => 'Disney+', 'valuta' => 'EUR'],
            ['naziv' => 'HBO Max', 'valuta' => 'EUR'],
        ]);
    }

    public function index(Request $request, CurrencyConversionService $currencyConversion)
    {
        $klijent = $this->klijent($request);
        $subskripcije = Subskripcija::where('klijent_id', $klijent->id)
            ->with('kategorija')
            ->orderBy('sledeci_datum')
            ->get();

        return response()->json($subskripcije->map(function (Subskripcija $sub) use ($klijent, $currencyConversion) {
            $converted = $currencyConversion->convert(
                (float) $sub->iznos,
                $sub->valuta,
                $klijent->preferred_currency ?: 'RSD',
            );
            $sub->setAttribute('iznos_u_preferiranoj_valuti', $converted);
            $sub->setAttribute('preferirana_valuta', $klijent->preferred_currency ?: 'RSD');
            return $sub;
        }));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'naziv' => ['required', 'string', 'max:255'],
            'iznos' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'valuta' => ['required', 'string', 'size:3'],
            'datum_naplate' => ['required', 'date'],
            'ucestalost' => ['required', 'in:mesecno,godisnje'],
            'kategorija_id' => ['required', 'exists:kategorije,id'],
            'aktivna' => ['sometimes', 'boolean'],
            'request_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $klijent = $this->klijent($request);
        $kategorija = Kategorija::whereKey($validated['kategorija_id'])
            ->where('tip', 'trosak')
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->first();

        if (!$kategorija) {
            return response()->json(['message' => 'Izabrana kategorija mora biti trošak i mora biti dostupna korisniku.'], 422);
        }

        $sledeci = CarbonImmutable::parse($validated['datum_naplate']);
        while ($sledeci->lessThan(CarbonImmutable::today())) {
            $sledeci = $validated['ucestalost'] === 'godisnje'
                ? $sledeci->addYearsNoOverflow()
                : $sledeci->addMonthNoOverflow();
        }
        $dan = (int) $sledeci->day;
        unset($validated['datum_naplate']);

        if (!empty($validated['request_id'])) {
            $existing = Subskripcija::where('klijent_id', $klijent->id)
                ->where('request_id', $validated['request_id'])
                ->first();
            if ($existing) {
                return response()->json($existing->load('kategorija'), 200);
            }
        }

        $subskripcija = Subskripcija::create([
            ...$validated,
            'naziv' => trim($validated['naziv']),
            'valuta' => strtoupper($validated['valuta']),
            'dan_naplate' => $dan,
            'sledeci_datum' => $sledeci,
            'klijent_id' => $klijent->id,
        ]);

        return response()->json($subskripcija->load('kategorija'), 201);
    }

    public function update(Request $request, string $id)
    {
        $klijent = $this->klijent($request);
        $subskripcija = Subskripcija::where('klijent_id', $klijent->id)->findOrFail($id);

        $validated = $request->validate([
            'naziv' => ['sometimes', 'required', 'string', 'max:255'],
            'iznos' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'gt:0'],
            'valuta' => ['sometimes', 'required', 'string', 'size:3'],
            'datum_naplate' => ['sometimes', 'required', 'date'],
            'ucestalost' => ['sometimes', 'required', 'in:mesecno,godisnje'],
            'kategorija_id' => ['sometimes', 'required', 'exists:kategorije,id'],
            'aktivna' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['kategorija_id'])) {
            $kategorija = Kategorija::whereKey($validated['kategorija_id'])
                ->where('tip', 'trosak')
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
                ->exists();
            if (!$kategorija) {
                return response()->json(['message' => 'Izabrana kategorija mora biti trošak i mora biti dostupna korisniku.'], 422);
            }
        }

        if (isset($validated['valuta'])) {
            $validated['valuta'] = strtoupper($validated['valuta']);
        }
        if (isset($validated['datum_naplate'])) {
            $sledeci = CarbonImmutable::parse($validated['datum_naplate']);
            $dan = (int) $sledeci->day;
            $ucestalost = $validated['ucestalost'] ?? $subskripcija->ucestalost;
            while ($sledeci->lessThan(CarbonImmutable::today())) {
                $sledeci = $ucestalost === 'godisnje'
                    ? $sledeci->addYearsNoOverflow()
                    : $sledeci->addMonthNoOverflow();
            }
            $validated['sledeci_datum'] = $sledeci;
            unset($validated['datum_naplate']);
            $validated['dan_naplate'] = $dan;
        }

        $subskripcija->update($validated);
        return response()->json($subskripcija->fresh('kategorija'));
    }

    public function destroy(Request $request, string $id)
    {
        $klijent = $this->klijent($request);
        $subskripcija = Subskripcija::where('klijent_id', $klijent->id)->findOrFail($id);
        $subskripcija->delete();
        return response()->json(['poruka' => 'Subskripcija je obrisana']);
    }

    private function klijent(Request $request): Klijent
    {
        $klijent = Klijent::where('user_id', $request->user()->id)->first();
        abort_if(!$klijent, 403, 'Korisnik nije klijent.');
        return $klijent;
    }

}
