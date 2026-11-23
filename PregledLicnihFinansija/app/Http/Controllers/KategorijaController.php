<?php

namespace App\Http\Controllers;

use App\Models\Kategorija;
use Illuminate\Http\Request;

class KategorijaController extends Controller
{
    
    public function index(Request $request)
    {
        $kategorije = Kategorija::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id');
                if ($request->user()) {
                    $query->orWhere('user_id', $request->user()->id);
                }
            })
            ->orderBy('tip')
            ->orderBy('naziv')
            ->get();

        return response()->json($kategorije);
    }

   
    public function create()
    {
        //
    }

   
    public function store(Request $request)
    {
        $request->validate([
            'naziv' => ['required', 'string', 'max:255'],
            'tip' => 'required|in:prihod,trosak'
        ]);

        $postoji = Kategorija::where('user_id', $request->user()->id)
            ->whereRaw('LOWER(naziv) = ?', [mb_strtolower(trim($request->naziv))])
            ->where('tip', $request->tip)
            ->exists();

        if ($postoji) {
            return response()->json(['message' => 'Kategorija sa istim nazivom i tipom već postoji.'], 422);
        }

        $kategorija = Kategorija::create([
            'naziv' => trim($request->naziv),
            'tip' => $request->tip,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($kategorija, 201);
    }

   
    public function show(string $id)
    {
        $kategorija = Kategorija::findOrFail($id);
        if ($kategorija->user_id !== null && $kategorija->user_id !== request()->user()->id && !request()->user()->isAdmin()) {
            abort(403);
        }
        return response()->json($kategorija);
    }

    
    public function edit(string $id)
    {
        //
    }

    
    public function update(Request $request, string $id)
    {
        $kategorija = Kategorija::findOrFail($id);
        $kategorija->update($request->all());
        return response()->json($kategorija);
    }

    
    public function destroy(string $id)
    {
        $kategorija = Kategorija::findOrFail($id);

        if (!request()->user()->isAdmin() && $kategorija->user_id !== request()->user()->id) {
            abort(403);
        }

        $kategorija->delete();
        return response()->json(['poruka' => 'Kategorija je obrisana']);
    }
}
