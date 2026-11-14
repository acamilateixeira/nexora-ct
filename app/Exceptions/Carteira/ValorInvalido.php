<?php

declare(strict_types=1);

namespace App\Exceptions\Carteira;

final class ValorInvalido extends CarteiraException
{
    public static function ilegivel(): self
    {
        return new self('Informe um valor válido, por exemplo 10,50.', 'valor_invalido');
    }

    public static function naoPositivo(): self
    {
        return new self('Informe um valor maior que R$ 0,00.', 'valor_invalido');
    }

    public static function grandeDemais(): self
    {
        return new self('Esse valor passa do limite permitido.', 'valor_invalido');
    }
}
