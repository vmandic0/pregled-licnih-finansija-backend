<?php

namespace App\Http\Controllers;

use App\Models\Klijent;
use App\Models\Transakcija;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class IzvestajController extends Controller
{
    private function getPodatkeIzvestaja(Klijent $klijent, int $mesec, int $godina): array
    {
        $transakcije = Transakcija::where('klijent_id', $klijent->id)
            ->whereMonth('datum', $mesec)
            ->whereYear('datum', $godina)
            ->with('kategorija')
            ->orderBy('datum', 'desc')
            ->get();

        $prihodi = round($transakcije->filter(fn($t) => $t->kategorija->tip === 'prihod')->sum(fn($t) => (float) $t->kolicina), 2);
        $troskovi = round($transakcije->filter(fn($t) => $t->kategorija->tip === 'trosak')->sum(fn($t) => (float) $t->kolicina), 2);

        return [
            'mesec' => $mesec,
            'godina' => $godina,
            'transakcije' => $transakcije,
            'ukupni_prihodi' => $prihodi,
            'ukupni_troskovi' => $troskovi,
            'bilans' => $prihodi - $troskovi,
        ];
    }

    private function getPodatkeGodisnjeg(Klijent $klijent, int $godina): array
    {
        $mesecniPodaci = [];
        $ukupniPrihodi = 0;
        $ukupniTroskovi = 0;

        for ($mesec = 1; $mesec <= 12; $mesec++) {
            $podaci = $this->getPodatkeIzvestaja($klijent, $mesec, $godina);
            $mesecniPodaci[] = [
                'mesec' => $mesec,
                'ukupni_prihodi' => $podaci['ukupni_prihodi'],
                'ukupni_troskovi' => $podaci['ukupni_troskovi'],
                'bilans' => $podaci['bilans'],
            ];
            $ukupniPrihodi += $podaci['ukupni_prihodi'];
            $ukupniTroskovi += $podaci['ukupni_troskovi'];
        }

        return [
            'godina' => $godina,
            'meseci' => $mesecniPodaci,
            'ukupni_prihodi' => $ukupniPrihodi,
            'ukupni_troskovi' => $ukupniTroskovi,
            'bilans' => $ukupniPrihodi - $ukupniTroskovi,
        ];
    }

    // Use case 7 - Mesečni izveštaj JSON
    public function mesecni(Request $request)
    {
        $request->validate([
            'mesec' => 'required|integer|min:1|max:12',
            'godina' => 'required|integer|min:2000|max:2100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $podaci = $this->getPodatkeIzvestaja($klijent, $request->mesec, $request->godina);

        return response()->json($podaci);
    }

    // Use case 14 - Godišnji izveštaj JSON
    public function godisnji(Request $request)
    {
        $request->validate([
            'godina' => 'required|integer|min:2000|max:2100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        return response()->json($this->getPodatkeGodisnjeg($klijent, $request->godina));
    }

    // Export mesečnog izveštaja kao PDF
    public function mesecniPDF(Request $request)
    {
        $request->validate([
            'mesec' => 'required|integer|min:1|max:12',
            'godina' => 'required|integer|min:2000|max:2100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $podaci = $this->getPodatkeIzvestaja($klijent, $request->mesec, $request->godina);
        $pdf = Pdf::loadView('izvestaji.mesecni', $podaci);

        return $pdf->download("izvestaj_{$request->godina}_{$request->mesec}.pdf");
    }

    // Export godišnjeg izveštaja kao PDF
    public function godisnjiPDF(Request $request)
    {
        $request->validate([
            'godina' => 'required|integer|min:2000|max:2100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $podaci = $this->getPodatkeGodisnjeg($klijent, $request->godina);
        $pdf = Pdf::loadView('izvestaji.godisnji', $podaci);

        return $pdf->download("izvestaj_{$request->godina}.pdf");
    }

    // Export mesečnog izveštaja kao CSV
    public function mesecniCSV(Request $request)
    {
        $request->validate([
            'mesec' => 'required|integer|min:1|max:12',
            'godina' => 'required|integer|min:2000|max:2100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $podaci = $this->getPodatkeIzvestaja($klijent, $request->mesec, $request->godina);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=izvestaj_{$request->godina}_{$request->mesec}.csv",
        ];

        $callback = function() use ($podaci) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Datum', 'Kategorija', 'Tip', 'Iznos']);

            foreach ($podaci['transakcije'] as $t) {
                fputcsv($file, [
                    $t->id,
                    $t->datum,
                    $t->kategorija->naziv,
                    $t->kategorija->tip,
                    $t->kolicina,
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['', '', '', 'Ukupni prihodi', $podaci['ukupni_prihodi']]);
            fputcsv($file, ['', '', '', 'Ukupni troškovi', $podaci['ukupni_troskovi']]);
            fputcsv($file, ['', '', '', 'Bilans', $podaci['bilans']]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Export godišnjeg izveštaja kao CSV
    public function godisnjiCSV(Request $request)
    {
        $request->validate([
            'godina' => 'required|integer|min:2000|max:2100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent) {
            return response()->json(['message' => 'Korisnik nije klijent.'], 403);
        }

        $podaci = $this->getPodatkeGodisnjeg($klijent, $request->godina);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=izvestaj_{$request->godina}.csv",
        ];

        $callback = function() use ($podaci) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Mesec', 'Ukupni prihodi', 'Ukupni troškovi', 'Bilans']);

            foreach ($podaci['meseci'] as $m) {
                fputcsv($file, [
                    $m['mesec'],
                    $m['ukupni_prihodi'],
                    $m['ukupni_troskovi'],
                    $m['bilans'],
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['UKUPNO', $podaci['ukupni_prihodi'], $podaci['ukupni_troskovi'], $podaci['bilans']]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}