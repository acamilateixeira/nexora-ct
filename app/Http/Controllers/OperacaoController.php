<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Carteira\DevolverTransferencia;
use App\Actions\Carteira\SolicitarEstorno;
use App\Http\Requests\EstornoRequest;
use App\Http\Requests\SolicitacaoEstornoRequest;
use App\Models\Operacao;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OperacaoController extends Controller
{
    public function show(Operacao $operacao): View
    {
        $this->authorize('view', $operacao);

        $operacao->load(['origem.user', 'destino.user', 'estorna', 'estorno', 'solicitacaoEstorno.solicitante']);

        return view('operacoes.show', compact('operacao'));
    }

    public function estornar(EstornoRequest $request, #[CurrentUser] User $user, Operacao $operacao, DevolverTransferencia $devolver): RedirectResponse
    {
        $motivo = $request->input('motivo');
        $devolver->handle($operacao, $user, is_string($motivo) ? $motivo : null);

        return redirect()->route('operacoes.show', $operacao)
            ->with('status', 'Valor devolvido para ' . rtrim($operacao->origem?->user?->nomeAbreviado() ?? '', '.') . '.');
    }

    public function solicitarEstorno(SolicitacaoEstornoRequest $request, #[CurrentUser] User $user, Operacao $operacao, SolicitarEstorno $solicitar): RedirectResponse
    {
        $solicitar->handle($operacao, $user, (string) $request->input('motivo'));

        return redirect()->route('operacoes.show', $operacao)
            ->with('status', 'Contestação enviada para análise do atendimento.');
    }
}
