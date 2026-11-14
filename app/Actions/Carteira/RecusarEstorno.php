<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Exceptions\Carteira\OperacaoNaoEstornavel;
use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\SolicitacaoEstorno;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecusarEstorno
{
    public function handle(Operacao $transferencia, string $motivo, string $operador): SolicitacaoEstorno
    {
        $solicitacao = DB::transaction(function () use ($transferencia, $motivo) {
            $destino = Carteira::lockForUpdate()->findOrFail($transferencia->carteira_destino_id);
            Operacao::lockForUpdate()->findOrFail($transferencia->id);
            $solicitacao = SolicitacaoEstorno::where('operacao_id', $transferencia->id)->lockForUpdate()->firstOrFail();

            if (! $solicitacao->estaPendente()) {
                throw OperacaoNaoEstornavel::solicitacaoJaRespondida();
            }

            $destino->liberar(Money::ofCents($solicitacao->valor_bloqueado));
            $destino->save();

            $solicitacao->update([
                'status' => SolicitacaoEstorno::STATUS_RECUSADA,
                'motivo_recusa' => $motivo,
                'respondida_em' => now(),
            ]);

            return $solicitacao;
        }, 3);

        Log::info('carteira.estorno_negado', ['operacao' => $transferencia->uuid, 'operador' => $operador]);

        return $solicitacao;
    }
}
