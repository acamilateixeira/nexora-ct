<?php

declare(strict_types=1);

namespace App\Exceptions\Carteira;

final class DestinatarioInvalido extends CarteiraException
{
    public static function naoEncontrado(): self
    {
        return new self('Não encontramos uma conta com esse e-mail. Confira se está escrito certo.', 'destinatario_nao_encontrado', 'email');
    }

    public static function proprioUsuario(): self
    {
        return new self('Não dá para transferir para você. Para colocar dinheiro na sua carteira, use Depositar.', 'transferencia_para_si_mesmo', 'email');
    }
}
