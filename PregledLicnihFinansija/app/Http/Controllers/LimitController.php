<?php

namespace App\Http\Controllers;

use App\Models\Limit;
use App\Models\Klijent;
use App\Services\CurrencyConversionService;
use Illuminate\Http\Request;

class LimitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $limiti = Limit::where('user_id', $request->user()->id)->with('kategorija')->get();
        return response()->json($limiti);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, CurrencyConversionService $currencyConversion)
    {
         $request->validate([
            'kategorija_id' => 'required|exists:kategorije,id',
            'iznos' => 'required|numeric|min:0',
        ]);

        $klijent = Klijent::where('user_id', $request->user()->id)->first();
        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $limit = Limit::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'kategorija_id' => $request->kategorija_id,
            ],
            [
                'iznos' => $currencyConversion->convert(
                    (float) $request->iznos,
                    $klijent->preferred_currency ?: 'RSD',
                    'RSD',
                ),
                'valuta' => 'RSD',
            ]
        );

        return response()->json($limit, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request ,string $id)
    {
        $limit = Limit::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($limit);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, CurrencyConversionService $currencyConversion)
    {
        $request->validate([
            'iznos' => 'required|numeric|min:0',
        ]);
        $limit = Limit::where('user_id', $request->user()->id)->findOrFail($id);
        $klijent = Klijent::where('user_id', $request->user()->id)->first();
        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }
        $limit->update([
            'iznos' => $currencyConversion->convert(
                (float) $request->iznos,
                $klijent->preferred_currency ?: 'RSD',
                'RSD',
            ),
            'valuta' => 'RSD',
        ]);
        return response()->json($limit);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $limit = Limit::where('user_id', $request->user()->id)->findOrFail($id);
        $limit->delete();
        return response()->json(['poruka' => 'Limit je obrisan']);
    }
}
