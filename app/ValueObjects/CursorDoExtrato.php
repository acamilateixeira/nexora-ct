<?php

declare(strict_types=1);

namespace App\ValueObjects;

use DateTimeImmutable;

final class CursorDoExtrato
{
    public static function ehValido(mixed $codificado): bool
    {
        if (! is_string($codificado) || $codificado === '') {
            return false;
        }

        $dados = json_decode((string) base64_decode(str_replace(['-', '_'], ['+', '/'], $codificado), true), true);

        $formato = is_array($dados)
            && array_keys($dados) === ['created_at', 'id', '_pointsToNextItems']
            && is_string($dados['created_at'])
            && is_int($dados['id'])
            && is_bool($dados['_pointsToNextItems']);

        if (! $formato) {
            return false;
        }

        $data = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dados['created_at']);

        return $data !== false && $data->format('Y-m-d H:i:s') === $dados['created_at'];
    }
}
