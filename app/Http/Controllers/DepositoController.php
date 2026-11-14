<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Carteira\Depositar;
use App\Http\Requests\DepositoRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepositoController extends Controller
{
    public function create(Request $request): View
    {
        $valor = $request->query('valor');

        return view('depositos.create', [
            'idempotencyKey' => (string) Str::uuid(),
            'valor' => old('valor', is_string($valor) ? $valor : ''),
        ]);
    }

    public function revisar(DepositoRequest $request, #[CurrentUser] User $user): View
    {
        $valor = $request->valor()->assertPositive();
        $saldo = $user->carteiraOuFalha()->saldo;

        return view('depositos.revisar', [
            'valor' => $valor,
            'saldo' => $saldo,
            'saldoDepois' => $saldo->add($valor),
            'idempotencyKey' => (string) $request->input('idempotency_key'),
        ]);
    }

    public function store(DepositoRequest $request, #[CurrentUser] User $user, Depositar $depositar): RedirectResponse
    {
        $operacao = $depositar->handle(
            $user,
            $request->valor(),
            (string) $request->input('idempotency_key')
        );

        $mensagem = $operacao->wasRecentlyCreated
            ? 'Depósito de ' . $operacao->valor->format() . ' realizado.'
            : 'Esse depósito já tinha sido registrado. Nenhum valor novo foi lançado.';

        return redirect()->route('dashboard')->with('status', $mensagem);
    }
}
