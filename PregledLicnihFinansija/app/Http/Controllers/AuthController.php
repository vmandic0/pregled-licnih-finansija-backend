<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Administrator;
use App\Models\Klijent;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    //Registracija
    public function register(Request $request)
    {
        // Preuzimanje podatak iz request i validacija da li su okej
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        //Pravljenje objekta user koji je po defaultu klijent
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'klijent',
        ]);
        
        Klijent::create([
            'user_id' => $user->id,
            'net_worth' => 0,
            'premium_klijent' => false,
            'preferred_currency' => 'RSD',
        ]);
        //Automatski login za klijenta
        Auth::login($user);
        //Vraća se json sa porukom o uspešnoj registraciji
        return response()->json([
            'message' => 'Uspešna registracija!',
            'user' => $user,
        ], 201);
    }

    //Prijava
    public function login(Request $request)
        {
            $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();
                $user->load('klijent');
                $token = $user->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'message' => 'Uspešna prijava!',
                    'token' => $token,
                    'user' => $user,
                ]);
            }

            return response()->json([
                'message' => 'Pogrešan email ili lozinka.',
            ], 401);
        }

    //Odjava
   public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Uspešno ste se odjavili.',
        ]);
    }

    //Zaboravljena lozinka - slanje linka za reset
    public function zaboravljenaLozinka(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) {
        // FRONTEND_URL treba da bude podešen u .env (npr. https://tvoj-frontend.netlify.app)
        // Link vodi na FRONTEND stranicu sa formom za novu lozinku, ne na API rutu direktno.
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        return $frontendUrl . '/reset-lozinka?token=' . $token . '&email=' . urlencode($user->email);
        });

        $status = \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        if($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT){
             return response()->json(['poruka' => 'Reset link je poslat na email.']);
        }

         return response()->json(['poruka' => 'Greška pri slanju reset linka.'], 400);
    }

    //Reset lozinke
    public function resetujLozinku(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );
       
        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json(['poruka' => 'Lozinka je uspešno promenjena.']);
        }

        return response()->json(['poruka' => 'Greška pri resetovanju lozinke.'], 400);
    }

}
