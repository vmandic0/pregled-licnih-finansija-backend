<?php

namespace App\Http\Middleware;

use App\Models\Klijent;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsPremium
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isAdmin()) {
            return $next($request);
        }

        $klijent = Klijent::where('user_id', $user->id)->first();

        if (!$klijent || !$klijent->isPremium()) {
            return response()->json([
                'message' => 'Samo premium korisnici mogu da pristupe ovoj funkcionalnosti.'
            ], 403);
        }

        return $next($request);
    }
}
