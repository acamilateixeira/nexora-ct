<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Carteira\Transferir;
use App\Http\Requests\TransferenciaRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransferenciaController extends Controller
{
    public function create(Request $request, #[CurrentUser] User $user): View
    {
        return view('transferencias.create', [
            'idempotencyKey' => (string) Str::uuid(),
            'email' => old('email', self::stringOrEmpty($request->query('email'))),
            'valor' => old('valor', self::stringOrEmpty($request->query('valor'))),
            'saldo' => $user->carteiraOuFalha()->disponivelParaTransferir(),
        ]);
    }

    public function revisar(TransferenciaRequest $request, #[CurrentUser] User $user, Transferir $transferir): View
    {
        $valor = $request->valor()->assertPositive();
        $destinatario = $transferir->destinatario($user, (string) $request->input('email'));

        return view('transferencias.revisar', [
            'email' => $destinatario->email,
            'nome' => $destinatario->nomeAbreviado(),
            'valor' => $valor,
            'idempotencyKey' => (string) $request->input('idempotency_key'),
            'saldo' => $user->carteiraOuFalha()->disponivelParaTransferir(),
        ]);
    }

    public function store(TransferenciaRequest $request, #[CurrentUser] User $user, Transferir $transferir): RedirectResponse
    {
        $operacao = $transferir->handle(
            $user,
            (string) $request->input('email'),
            $request->valor(),
            (string) $request->input('idempotency_key')
        );

        $mensagem = $operacao->wasRecentlyCreated
            ? 'Transferência de ' . $operacao->valor->format() . ' realizada.'
            : 'Essa transferência já tinha sido realizada. Nenhum valor novo foi enviado.';

        return redirect()->route('operacoes.show', $operacao)->with('status', $mensagem);
    }

    private static function stringOrEmpty(mixed $valor): string
    {
        return is_string($valor) ? $valor : '';
    }
}
