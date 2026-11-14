<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Periodo;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PeriodoTest extends TestCase
{
    /** @var CarbonImmutable */
    private $hoje;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoje = CarbonImmutable::parse('2026-11-13 12:00:00', 'America/Sao_Paulo');
    }

    public function test_ultimos_30_dias_incluem_hoje_e_comecam_a_meia_noite(): void
    {
        $periodo = Periodo::daQuery([], $this->hoje);

        $this->assertSame('2026-10-15 00:00:00', $periodo->inicio()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-13 23:59:59', $periodo->fim()->format('Y-m-d H:i:s'));
        $this->assertSame('Últimos 30 dias', $periodo->rotulo());
        $this->assertNull($periodo->aviso());
    }

    public function test_limites_em_utc_respeitam_o_fuso_de_brasilia(): void
    {
        $periodo = Periodo::daQuery(['periodo' => 'personalizado', 'de' => '2026-10-01', 'ate' => '2026-10-31'], $this->hoje);

        $this->assertSame('2026-10-01 03:00:00', $periodo->inicio()->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 02:59:59', $periodo->fim()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_janela_de_exatamente_90_dias_e_aceita_e_91_nao(): void
    {
        $noventa = Periodo::daQuery(['periodo' => 'personalizado', 'de' => '2026-08-16', 'ate' => '2026-11-13'], $this->hoje);
        $noventaEUm = Periodo::daQuery(['periodo' => 'personalizado', 'de' => '2026-08-15', 'ate' => '2026-11-13'], $this->hoje);

        $this->assertSame('personalizado', $noventa->chave());
        $this->assertSame(Periodo::PADRAO, $noventaEUm->chave());
        $this->assertStringContainsString('no máximo 90 dias', (string) $noventaEUm->aviso());
    }

    #[DataProvider('datasInvalidas')]
    public function test_data_invalida_cai_no_padrao_com_aviso($de): void
    {
        $periodo = Periodo::daQuery(['periodo' => 'personalizado', 'de' => $de, 'ate' => '2026-11-01'], $this->hoje);

        $this->assertSame(Periodo::PADRAO, $periodo->chave());
        $this->assertNotNull($periodo->aviso());
    }

    /** @return array<string, array{0: mixed}> */
    public static function datasInvalidas(): array
    {
        return [
            '31 de fevereiro' => ['2026-02-31'],
            'formato brasileiro' => ['01/10/2026'],
            'array' => [['2026-10-01']],
            'vazia' => [''],
        ];
    }
}
