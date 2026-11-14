<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Depositar
{
    /** @var Idempotency */
    private $idempotency;

    public function __construct(Idempotency $idempotency)
    {
        $this->idempotency = $idempotency;
    }

    public function handle(User $user, Money $valor, string $idempotencyKey): Operacao
    {
        $valor->assertPositive();
        $hash = Idempotency::hash(Operacao::TIPO_DEPOSITO, (string) $valor->cents());

        try {
            [$operacao, $nova] = DB::transaction(function () use ($user, $valor, $idempotencyKey, $hash) {
                $carteira = Carteira::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

                $jaFeita = $this->idempotency->findExisting($user, $idempotencyKey, $hash);
                if ($jaFeita) {
                    return [$jaFeita, false];
                }

                $operacao = Operacao::create([
                    'tipo' => Operacao::TIPO_DEPOSITO,
                    'status' => Operacao::STATUS_CONCLUIDA,
                    'valor' => $valor,
                    'carteira_destino_id' => $carteira->id,
                    'criada_por' => $user->id,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $hash,
                ]);

                $operacao->creditar($carteira);

                return [$operacao, true];
            }, 3);
        } catch (QueryException $e) {
            return $this->idempotency->resolveDuplicate($e, $user, $idempotencyKey, $hash);
        }

        if ($nova) {
            Log::info('carteira.deposito', [
                'operacao' => $operacao->uuid,
                'carteira_id' => $operacao->carteira_destino_id,
                'valor' => $valor->cents(),
            ]);
        }

        return $operacao;
    }
}
