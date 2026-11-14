<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Exceptions\Carteira\OperacaoNaoEstornavel;
use App\Models\Operacao;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DevolverTransferencia
{
    public function __construct(private Estornar $estornar)
    {
    }

    public function handle(Operacao $transferencia, User $quemRecebeu, ?string $motivo): Operacao
    {
        if ($transferencia->tipo !== Operacao::TIPO_TRANSFERENCIA) {
            throw OperacaoNaoEstornavel::soTransferencia();
        }
        if ($transferencia->carteira_destino_id !== $quemRecebeu->carteira?->id) {
            throw new AuthorizationException('Só quem recebeu a transferência pode devolver.');
        }

        return $this->estornar->handle($transferencia, (string) $motivo, $quemRecebeu);
    }
}
