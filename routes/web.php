<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositoController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OperacaoController;
use App\Http\Controllers\TransferenciaController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'health'])->name('health');
Route::get('/ready', [HealthController::class, 'ready'])->name('ready');

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/depositos/novo', [DepositoController::class, 'create'])->name('depositos.create');
    Route::post('/depositos/revisar', [DepositoController::class, 'revisar'])
        ->middleware('throttle:operacoes')
        ->name('depositos.revisar');
    Route::post('/depositos', [DepositoController::class, 'store'])
        ->middleware('throttle:operacoes')
        ->name('depositos.store');

    Route::get('/transferencias/nova', [TransferenciaController::class, 'create'])->name('transferencias.create');
    Route::post('/transferencias/revisar', [TransferenciaController::class, 'revisar'])
        ->middleware('throttle:operacoes')
        ->name('transferencias.revisar');
    Route::post('/transferencias', [TransferenciaController::class, 'store'])
        ->middleware('throttle:operacoes')
        ->name('transferencias.store');

    Route::get('/operacoes/{operacao}', [OperacaoController::class, 'show'])->name('operacoes.show');
    Route::post('/operacoes/{operacao}/estorno', [OperacaoController::class, 'estornar'])
        ->middleware('throttle:operacoes')
        ->name('operacoes.estornar');
    Route::post('/operacoes/{operacao}/solicitacao-estorno', [OperacaoController::class, 'solicitarEstorno'])
        ->middleware('throttle:operacoes')
        ->name('operacoes.solicitar-estorno');
});

require __DIR__ . '/auth.php';
