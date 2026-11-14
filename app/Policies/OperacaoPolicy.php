<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Operacao;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OperacaoPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Operacao $operacao): bool
    {
        return $this->enviou($user, $operacao) || $this->recebeu($user, $operacao);
    }

    public function estornar(User $user, Operacao $operacao): bool
    {
        return $operacao->tipo === Operacao::TIPO_TRANSFERENCIA
            && ! $operacao->estaEstornada()
            && $this->recebeu($user, $operacao);
    }

    public function solicitarEstorno(User $user, Operacao $operacao): bool
    {
        return $operacao->tipo === Operacao::TIPO_TRANSFERENCIA
            && ! $operacao->estaEstornada()
            && $operacao->solicitacaoEstorno === null
            && $this->enviou($user, $operacao);
    }

    private function enviou(User $user, Operacao $operacao): bool
    {
        return $user->carteira !== null && $operacao->carteira_origem_id === $user->carteira->id;
    }

    private function recebeu(User $user, Operacao $operacao): bool
    {
        return $user->carteira !== null && $operacao->carteira_destino_id === $user->carteira->id;
    }
}
