<?php

use Illuminate\Support\Facades\Route;
use Hoangnh\Solana\Http\controllers\SolanaController;
Route::get('/test', function () {
    return "Test package sol";
});

Route::post('/solana/create-address', [SolanaController::class, 'createAddress']);
Route::post('/solana/deposit', [SolanaController::class, 'deposit']);
Route::post('/solana/withdraw', [SolanaController::class, 'withdraw']);
Route::post('/solana/transfer', [SolanaController::class, 'transfer']);