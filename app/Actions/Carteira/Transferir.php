<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Exceptions\Carteira\DestinatarioInvalido;
use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Transferir
{
    /** @var Idempotency */
    private $idempotency;

    public function __construct(Idempotency $idempotency)
    {
        $this->idempotency = $idempotency;
    }

    public function handle(User $remetente, string $emailDestino, Money $valor, string $idempotencyKey): Operacao
    {
        $valor->assertPositive();
        $destinatario = $this->destinatario($remetente, $emailDestino);

        $hash = Idempotency::hash(Operacao::TIPO_TRANSFERENCIA, (string) $destinatario->id, (string) $valor->cents());

        $ids = Carteira::whereIn('user_id', [$remetente->id, $destinatario->id])->pluck('id')->all();

        try {
            [$operacao, $nova] = DB::transaction(function () use ($ids, $remetente, $destinatario, $valor, $idempotencyKey, $hash) {
                $carteiras = Carteira::whereIn('id', $ids)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('user_id');

                $jaFeita = $this->idempotency->findExisting($remetente, $idempotencyKey, $hash);
                if ($jaFeita) {
                    return [$jaFeita, false];
                }

                $origem = $carteiras->get($remetente->id);
                $destino = $carteiras->get($destinatario->id);
                if (! $destino) {
                    throw DestinatarioInvalido::naoEncontrado();
                }
                if (! $origem) {
                    throw (new ModelNotFoundException())->setModel(Carteira::class, [$remetente->id]);
                }

                $operacao = Operacao::create([
                    'tipo' => Operacao::TIPO_TRANSFERENCIA,
                    'status' => Operacao::STATUS_CONCLUIDA,
                    'valor' => $valor,
                    'carteira_origem_id' => $origem->id,
                    'carteira_destino_id' => $destino->id,
                    'criada_por' => $remetente->id,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $hash,
                ]);

                $operacao->debitar($origem);
                $operacao->creditar($destino);

                return [$operacao, true];
            }, 3);
        } catch (QueryException $e) {
            return $this->idempotency->resolveDuplicate($e, $remetente, $idempotencyKey, $hash);
        }

        if ($nova) {
            Log::info('carteira.transferencia', [
                'operacao' => $operacao->uuid,
                'carteira_origem_id' => $operacao->carteira_origem_id,
                'carteira_destino_id' => $operacao->carteira_destino_id,
                'valor' => $valor->cents(),
            ]);
        }

        return $operacao;
    }

    public function destinatario(User $remetente, string $email): User
    {
        $destinatario = User::where('email', $email)->first();
        if (! $destinatario || ! $destinatario->carteira) {
            throw DestinatarioInvalido::naoEncontrado();
        }
        if ($destinatario->id === $remetente->id) {
            throw DestinatarioInvalido::proprioUsuario();
        }

        return $destinatario;
    }
}
