<?php

use App\Http\Controllers\GejmifikacijaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KategorijaController;
use App\Http\Controllers\TransakcijaController;
use App\Http\Controllers\LimitController;
use App\Http\Controllers\KreditController;
use App\Http\Controllers\KlijentController;
use App\Http\Controllers\KonverzijaController;
use App\Http\Controllers\IzvestajController;
use App\Http\Controllers\GrupnaTransakcijaController;
use App\Http\Controllers\ProfilnaSlikaController;
use App\Http\Controllers\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\IsAdmin;


use App\Http\Middleware\IsPremium;

//Health check - koristi se za automatski "ping" da Supabase baza ne pauzira zbog neaktivnosti
Route::get('/health', function () {
    \Illuminate\Support\Facades\DB::select('SELECT 1');
    return response()->json(['status' => 'ok']);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//Zaboravljena lozinka
Route::post('/zaboravljena-lozinka', [AuthController::class, 'zaboravljenaLozinka']);
Route::post('/reset-lozinka', [AuthController::class, 'resetujLozinku']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Kategorije
    Route::get('/kategorije', [KategorijaController::class, 'index']);
    Route::get('/kategorije/{id}', [KategorijaController::class, 'show']);
    
    Route::middleware(IsAdmin::class)->group(function () {
        Route::apiResource('kategorije', KategorijaController::class)->except(['index', 'show']);

        Route::get('/admin/korisnici', [AdminController::class, 'korisnici']);
        Route::put('/admin/korisnici/{id}/uloga', [AdminController::class, 'promeniUlogu']);
        Route::get('/admin/analitika', [AdminController::class, 'analitika']);
        Route::put('/admin/korisnici/{id}/premium', [AdminController::class, 'promeniPremium']);
    });

    // Transakcije
    Route::get('/transakcije', [TransakcijaController::class, 'index']);
    Route::post('/transakcije', [TransakcijaController::class, 'store']);
    Route::get('/transakcije/filter', [TransakcijaController::class, 'filter']);
    Route::delete('/transakcije/{id}', [TransakcijaController::class, 'destroy']);

    Route::apiResource('limiti', LimitController::class)->except(['create', 'edit']);

    // samo premium korisnici
    Route::middleware(IsPremium::class)->group(function () {
        Route::get('/krediti', [KreditController::class, 'index']);
        Route::post('/krediti', [KreditController::class, 'store']);
        Route::patch('/krediti/{id}', [KreditController::class, 'update']);
        Route::delete('/krediti/{id}', [KreditController::class, 'destroy']);
        Route::get('/net-worth', [KlijentController::class, 'netWorth']);
        Route::get('/izvestaj/mesecni', [IzvestajController::class, 'mesecni']);
        Route::get('/izvestaj/godisnji', [IzvestajController::class, 'godisnji']);
        Route::get('/izvestaj/mesecni/pdf', [IzvestajController::class, 'mesecniPDF']);
        Route::get('/izvestaj/mesecni/csv', [IzvestajController::class, 'mesecniCSV']);
        Route::get('/izvestaj/godisnji/pdf', [IzvestajController::class, 'godisnjiPDF']);
        Route::get('/izvestaj/godisnji/csv', [IzvestajController::class, 'godisnjiCSV']);
        Route::get('/grupe', [GrupnaTransakcijaController::class, 'index']);
        Route::post('/grupe', [GrupnaTransakcijaController::class, 'store']);
        Route::get('/grupe/{id}', [GrupnaTransakcijaController::class, 'show']);
        Route::post('/grupe/{id}/uplati', [GrupnaTransakcijaController::class, 'uplatiUdeo']);
        Route::post('/grupe/{id}/dodaj-clana', [GrupnaTransakcijaController::class, 'dodajClana']);
        Route::delete('/grupe/{id}', [GrupnaTransakcijaController::class, 'destroy']);
    });

    Route::get('/profil', [KlijentController::class, 'profil']);

    // Konverzija valuta
    Route::get('/valute', [KonverzijaController::class, 'valute']);
    Route::post('/konvertuj', [KonverzijaController::class, 'konvertuj']);
    Route::post('/promeni-valutu', [KonverzijaController::class, 'promeniValutu']);

    // Profilna slika
    Route::post('/profilna-slika', [ProfilnaSlikaController::class, 'upload']);
    Route::get('/profilna-slika', [ProfilnaSlikaController::class, 'show']);
    Route::delete('/profilna-slika', [ProfilnaSlikaController::class, 'destroy']);

    // Gejmifikacija
    Route::get('/gejmifikacija/status', [GejmifikacijaController::class, 'status']);
    Route::get('/gejmifikacija/mesecni-cilj', [GejmifikacijaController::class, 'mesecniCilj']);

});
