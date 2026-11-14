<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Exceptions\Carteira\OperacaoNaoEstornavel;
use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\SolicitacaoEstorno;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SolicitarEstorno
{
    public function handle(Operacao $transferencia, User $solicitante, string $motivo): SolicitacaoEstorno
    {
        try {
            $solicitacao = DB::transaction(function () use ($transferencia, $solicitante, $motivo) {
                $destino = Carteira::lockForUpdate()->findOrFail($transferencia->carteira_destino_id);
                $transferencia = Operacao::lockForUpdate()->findOrFail($transferencia->id);

                if ($transferencia->carteira_origem_id !== $solicitante->carteira?->id) {
                    throw new AuthorizationException('Só quem enviou a transferência pode contestar.');
                }
                if ($transferencia->tipo !== Operacao::TIPO_TRANSFERENCIA) {
                    throw OperacaoNaoEstornavel::soTransferencia();
                }
                if ($transferencia->estaEstornada()) {
                    throw OperacaoNaoEstornavel::jaEstornada();
                }
                if (SolicitacaoEstorno::where('operacao_id', $transferencia->id)->exists()) {
                    throw OperacaoNaoEstornavel::estornoJaSolicitado();
                }

                $bloqueado = $destino->bloquearAte($transferencia->valor);
                $destino->save();

                return SolicitacaoEstorno::create([
                    'operacao_id' => $transferencia->id,
                    'solicitada_por' => $solicitante->id,
                    'motivo' => $motivo,
                    'valor_bloqueado' => $bloqueado->cents(),
                    'status' => SolicitacaoEstorno::STATUS_PENDENTE,
                ]);
            }, 3);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw OperacaoNaoEstornavel::estornoJaSolicitado();
            }
            throw $e;
        }

        Log::info('carteira.estorno_solicitado', ['operacao' => $transferencia->uuid, 'solicitante' => $solicitante->id]);

        return $solicitacao;
    }
}
