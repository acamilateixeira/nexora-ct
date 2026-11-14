<?php

declare(strict_types=1);

namespace App\Exceptions\Carteira;

final class OperacaoNaoEstornavel extends CarteiraException
{
    public static function jaEstornada(): self
    {
        return new self('Esta operação já foi estornada.', 'operacao_ja_estornada', 'motivo');
    }

    public static function eUmEstorno(): self
    {
        return new self('Não é possível estornar um estorno.', 'estorno_de_estorno', 'motivo');
    }

    public static function estornoJaSolicitado(): self
    {
        return new self('Esta transferência já foi contestada.', 'estorno_ja_solicitado', 'motivo');
    }

    public static function solicitacaoJaRespondida(): self
    {
        return new self('Esta contestação já foi analisada.', 'solicitacao_ja_respondida', 'motivo');
    }

    public static function soTransferencia(): self
    {
        return new self('Só transferências podem ser contestadas. Para depósitos, fale com o atendimento.', 'estorno_nao_solicitavel', 'motivo');
    }
}
