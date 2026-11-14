<?php

declare(strict_types=1);

namespace App\Exceptions\Carteira;

final class IdempotencyKeyReused extends CarteiraException
{
    public static function criar(): self
    {
        return new self(
            'Esse formulário já foi usado em outra operação. Abra a tela de novo para continuar.',
            'idempotency_key_reused'
        );
    }
}
