<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Exceptions\Carteira\IdempotencyKeyReused;
use App\Models\Operacao;
use App\Models\User;
use Illuminate\Database\QueryException;

class Idempotency
{
    private const UNIQUE_CONSTRAINT = 'operacoes_criada_por_idempotency_key_unique';

    public static function hash(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    public function findExisting(User $user, string $idempotencyKey, string $hash): ?Operacao
    {
        $operacao = Operacao::where('criada_por', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();

        return $operacao ? $this->ensureSameRequest($operacao, $hash) : null;
    }

    public function resolveDuplicate(QueryException $e, User $user, string $idempotencyKey, string $hash): Operacao
    {
        $duplicada = ($e->errorInfo[1] ?? null) === 1062
            && strpos($e->getMessage(), self::UNIQUE_CONSTRAINT) !== false;

        $operacao = $duplicada
            ? Operacao::where('criada_por', $user->id)->where('idempotency_key', $idempotencyKey)->first()
            : null;

        if (! $operacao) {
            throw $e;
        }

        return $this->ensureSameRequest($operacao, $hash);
    }

    private function ensureSameRequest(Operacao $operacao, string $hash): Operacao
    {
        if (! hash_equals((string) $operacao->request_hash, $hash)) {
            throw IdempotencyKeyReused::criar();
        }

        return $operacao;
    }
}
