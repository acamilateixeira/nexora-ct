<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Carbon\CarbonImmutable;

final class Periodo
{
    public const PADRAO = '30';

    public const RAPIDOS = ['7', '30', '90'];

    public const MAXIMO_DE_DIAS = 90;

    /** @var CarbonImmutable */
    private $inicio;

    /** @var CarbonImmutable */
    private $fim;

    /** @var string */
    private $chave;

    /** @var string|null */
    private $aviso;

    private function __construct(CarbonImmutable $inicio, CarbonImmutable $fim, string $chave, ?string $aviso = null)
    {
        $this->inicio = $inicio->startOfDay();
        $this->fim = $fim->endOfDay();
        $this->chave = $chave;
        $this->aviso = $aviso;
    }

    /** @param array<string, mixed> $query */
    public static function daQuery(array $query, CarbonImmutable $hoje): self
    {
        $chave = $query['periodo'] ?? self::PADRAO;

        if (in_array($chave, self::RAPIDOS, true)) {
            return self::ultimosDias((int) $chave, $hoje);
        }

        if ($chave !== 'personalizado') {
            return self::ultimosDias((int) self::PADRAO, $hoje, 'Período inválido. Mostrando os últimos 30 dias.');
        }

        $de = self::data($query['de'] ?? null, $hoje);
        $ate = self::data($query['ate'] ?? null, $hoje);

        if ($de === null || $ate === null) {
            return self::ultimosDias((int) self::PADRAO, $hoje, 'Informe as duas datas do período. Mostrando os últimos 30 dias.');
        }
        if ($de->greaterThan($ate)) {
            return self::ultimosDias((int) self::PADRAO, $hoje, 'A data inicial é depois da final. Mostrando os últimos 30 dias.');
        }
        if ($ate->greaterThan($hoje)) {
            return self::ultimosDias((int) self::PADRAO, $hoje, 'O período não pode terminar no futuro. Mostrando os últimos 30 dias.');
        }
        if ($de->diffInDays($ate) + 1 > self::MAXIMO_DE_DIAS) {
            return self::ultimosDias((int) self::PADRAO, $hoje, 'O período pode ter no máximo 90 dias. Mostrando os últimos 30 dias.');
        }

        return new self($de, $ate, 'personalizado');
    }

    private static function ultimosDias(int $dias, CarbonImmutable $hoje, ?string $aviso = null): self
    {
        return new self($hoje->subDays($dias - 1), $hoje, (string) $dias, $aviso);
    }

    private static function data(mixed $valor, CarbonImmutable $hoje): ?CarbonImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }

        $data = CarbonImmutable::createFromFormat('!Y-m-d', $valor, $hoje->getTimezone());

        return $data !== null && $data->format('Y-m-d') === $valor ? $data : null;
    }

    public function inicio(): CarbonImmutable
    {
        return $this->inicio;
    }

    public function fim(): CarbonImmutable
    {
        return $this->fim;
    }

    public function chave(): string
    {
        return $this->chave;
    }

    public function aviso(): ?string
    {
        return $this->aviso;
    }

    public function rotulo(): string
    {
        return $this->chave === 'personalizado'
            ? $this->inicio->format('d/m/Y') . ' a ' . $this->fim->format('d/m/Y')
            : 'Últimos ' . $this->chave . ' dias';
    }
}
