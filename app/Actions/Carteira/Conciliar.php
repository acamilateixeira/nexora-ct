<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Models\Operacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Conciliar
{
    /** @return array<int, array{0: string, 1: array<string, mixed>}> */
    public function handle(): array
    {
        $problemas = array_merge(
            $this->saldosDivergentes(),
            $this->operacoesMalformadas(),
            $this->estornadasSemEstorno()
        );

        foreach ($problemas as [$tipo, $context]) {
            Log::critical($tipo, $context);
        }

        return $problemas;
    }

    /** @return array<int, array{0: string, 1: array<string, mixed>}> */
    private function saldosDivergentes(): array
    {
        return DB::table('carteiras')
            ->leftJoin('lancamentos', 'lancamentos.carteira_id', '=', 'carteiras.id')
            ->groupBy('carteiras.id', 'carteiras.saldo')
            ->havingRaw('carteiras.saldo <> COALESCE(SUM(lancamentos.valor), 0)')
            ->selectRaw('carteiras.id, carteiras.saldo, COALESCE(SUM(lancamentos.valor), 0) AS soma')
            ->get()
            ->map(function ($linha) {
                return ['carteira.saldo_divergente', [
                    'carteira_id' => (int) $linha->id,
                    'saldo' => (int) $linha->saldo,
                    'soma_lancamentos' => (int) $linha->soma,
                ]];
            })
            ->all();
    }

    /** @return array<int, array{0: string, 1: array<string, mixed>}> */
    private function operacoesMalformadas(): array
    {
        return DB::table('operacoes')
            ->leftJoin('lancamentos', 'lancamentos.operacao_id', '=', 'operacoes.id')
            ->groupBy('operacoes.id', 'operacoes.uuid', 'operacoes.valor', 'operacoes.carteira_origem_id', 'operacoes.carteira_destino_id')
            ->selectRaw('operacoes.uuid, operacoes.valor, operacoes.carteira_origem_id, operacoes.carteira_destino_id, COUNT(lancamentos.id) AS quantidade, COALESCE(SUM(lancamentos.valor), 0) AS soma')
            ->get()
            ->filter(function ($linha) {
                $quantidade = ($linha->carteira_origem_id ? 1 : 0) + ($linha->carteira_destino_id ? 1 : 0);
                $soma = ($linha->carteira_destino_id ? (int) $linha->valor : 0) - ($linha->carteira_origem_id ? (int) $linha->valor : 0);

                return (int) $linha->quantidade !== $quantidade || (int) $linha->soma !== $soma;
            })
            ->map(function ($linha) {
                return ['carteira.operacao_malformada', [
                    'operacao' => $linha->uuid,
                    'lancamentos' => (int) $linha->quantidade,
                    'soma_lancamentos' => (int) $linha->soma,
                ]];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array{0: string, 1: array<string, mixed>}> */
    private function estornadasSemEstorno(): array
    {
        return DB::table('operacoes AS original')
            ->leftJoin('operacoes AS estorno', 'estorno.estorna_id', '=', 'original.id')
            ->where('original.status', Operacao::STATUS_ESTORNADA)
            ->whereNull('estorno.id')
            ->pluck('original.uuid')
            ->map(function ($uuid) {
                return ['carteira.estornada_sem_estorno', ['operacao' => $uuid]];
            })
            ->all();
    }
}
