<?php

namespace App\Http\Controllers;

use App\Models\User;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\Request;

class ProfilnaSlikaController extends Controller
{
    private function getCloudinary(): Cloudinary
    {
        return new Cloudinary(env('CLOUDINARY_URL'));
    }

    // Upload profilne slike
    public function upload(Request $request)
    {
        $request->validate([
            'slika' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        /** @var User $user */
        $user = $request->user();

        $cloudinary = $this->getCloudinary();

        $rezultat = $cloudinary->uploadApi()->upload(
            $request->file('slika')->getRealPath(),
            [
                'folder' => 'profilne_slike',
                'public_id' => 'user_' . $user->id,
                'overwrite' => true,
            ]
        );

        $user->profile_image = $rezultat['secure_url'];
        $user->save();

        return response()->json([
            'message' => 'Profilna slika uspešno uploadovana!',
            'profile_image' => $user->profile_image,
        ]);
    }

    // Pregled profilne slike
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->profile_image) {
            return response()->json([
                'message' => 'Korisnik nema profilnu sliku.'
            ], 404);
        }

        return response()->json([
            'profile_image' => $user->profile_image,
        ]);
    }

    // Brisanje profilne slike
    public function destroy(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->profile_image) {
            return response()->json([
                'message' => 'Korisnik nema profilnu sliku.'
            ], 404);
        }

        $cloudinary = $this->getCloudinary();
        $cloudinary->uploadApi()->destroy('profilne_slike/user_' . $user->id);

        $user->profile_image = null;
        $user->save();

        return response()->json([
            'message' => 'Profilna slika uspešno obrisana. Tamara',

        ]);
    }
}