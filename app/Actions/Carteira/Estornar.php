<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Exceptions\Carteira\OperacaoNaoEstornavel;
use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\SolicitacaoEstorno;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Estornar
{
    public function handle(Operacao $original, string $motivo, ?User $solicitante, ?string $operador = null): Operacao
    {
        $estorno = DB::transaction(function () use ($original, $motivo, $solicitante, $operador) {
            $carteiras = Carteira::whereIn('id', array_filter([$original->carteira_origem_id, $original->carteira_destino_id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $original = Operacao::lockForUpdate()->findOrFail($original->id);

            if ($original->tipo === Operacao::TIPO_ESTORNO) {
                throw OperacaoNaoEstornavel::eUmEstorno();
            }
            if ($original->status === Operacao::STATUS_ESTORNADA) {
                throw OperacaoNaoEstornavel::jaEstornada();
            }

            $valor = $original->valor;
            $sai = $original->carteira_destino_id ? $carteiras[$original->carteira_destino_id] : null;
            $volta = $original->carteira_origem_id ? $carteiras[$original->carteira_origem_id] : null;

            $estorno = Operacao::create([
                'tipo' => Operacao::TIPO_ESTORNO,
                'status' => Operacao::STATUS_CONCLUIDA,
                'valor' => $valor,
                'carteira_origem_id' => $sai ? $sai->id : null,
                'carteira_destino_id' => $volta ? $volta->id : null,
                'estorna_id' => $original->id,
                'criada_por' => $solicitante ? $solicitante->id : null,
                'operador' => $operador,
                'motivo' => $motivo,
            ]);

            if ($sai) {
                $estorno->debitarMesmoSemSaldo($sai);
            }
            if ($volta) {
                $estorno->creditar($volta);
            }

            $original->status = Operacao::STATUS_ESTORNADA;
            $original->save();

            $contestacao = SolicitacaoEstorno::where('operacao_id', $original->id)
                ->where('status', SolicitacaoEstorno::STATUS_PENDENTE)
                ->lockForUpdate()
                ->first();
            if ($contestacao) {
                if ($sai && $contestacao->valor_bloqueado > 0) {
                    $sai->liberar(Money::ofCents($contestacao->valor_bloqueado));
                    $sai->save();
                }
                $contestacao->update(['status' => SolicitacaoEstorno::STATUS_ACEITA, 'respondida_em' => now()]);
            }

            return $estorno;
        }, 3);

        Log::info('carteira.estorno', [
            'operacao' => $estorno->uuid,
            'estorna' => $original->uuid,
            'valor' => $estorno->valor->cents(),
            'solicitante' => $solicitante ? $solicitante->id : null,
            'operador' => $operador,
            'motivo' => $motivo,
        ]);

        return $estorno;
    }
}
