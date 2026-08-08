<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;


Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Pregled ličnih finansija API',
    ]);
});

