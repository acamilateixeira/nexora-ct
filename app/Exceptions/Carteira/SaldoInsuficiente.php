<?php

declare(strict_types=1);

namespace App\Exceptions\Carteira;

use App\Models\Carteira;
use App\ValueObjects\Money;

final class SaldoInsuficiente extends CarteiraException
{
    public static function paraCarteira(Carteira $carteira, Money $valor): self
    {
        return new self('Saldo insuficiente para essa transferência.', 'saldo_insuficiente', 'valor', [
            'carteira_id' => $carteira->id,
            'saldo' => $carteira->saldo->cents(),
            'valor' => $valor->cents(),
        ]);
    }
}
