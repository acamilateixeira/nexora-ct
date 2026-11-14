<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Carteira\Depositar;
use App\Actions\Carteira\Estornar;
use App\Actions\Carteira\Transferir;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class ExtratoTest extends TestCase
{
    use RefreshDatabase;
    use PreparaCarteiras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00:00', 'UTC'));
    }

    public function test_padrao_mostra_os_ultimos_30_dias(): void
    {
        $helena = $this->usuarioComSaldo(0);
        $this->depositarEm($helena, '2026-10-14 15:00:00', '1,00');
        $this->depositarEm($helena, '2026-10-15 15:00:00', '2,00');

        $this->actingAs($helena)->get('/dashboard')
            ->assertSee('Últimos 30 dias')
            ->assertSee('+R$ 2,00')
            ->assertDontSee('+R$ 1,00');
    }

    public function test_periodo_personalizado_respeita_as_datas_no_fuso_de_brasilia(): void
    {
        $helena = $this->usuarioComSaldo(0);

        $this->depositarEm($helena, '2026-10-01 02:30:00', '3,00');
        $this->depositarEm($helena, '2026-10-01 03:30:00', '4,00');

        $this->actingAs($helena)->get('/dashboard?periodo=personalizado&de=2026-10-01&ate=2026-10-31')
            ->assertSee('01/10/2026 a 31/10/2026')
            ->assertSee('+R$ 4,00')
            ->assertDontSee('+R$ 3,00');
    }

    #[DataProvider('periodosInvalidos')]
    public function test_periodo_invalido_cai_nos_ultimos_30_dias_com_aviso(string $query, string $aviso): void
    {
        $helena = $this->usuarioComSaldo(0);

        $this->actingAs($helena)->get('/dashboard?' . $query)
            ->assertOk()
            ->assertSee('Últimos 30 dias')
            ->assertSee($aviso);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function periodosInvalidos(): array
    {
        return [
            'período desconhecido' => ['periodo=365', 'Período inválido.'],
            'sem as datas' => ['periodo=personalizado', 'Informe as duas datas do período.'],
            'data que não existe' => ['periodo=personalizado&de=2026-02-31&ate=2026-03-10', 'Informe as duas datas do período.'],
            'formato errado' => ['periodo=personalizado&de=01/10/2026&ate=2026-10-31', 'Informe as duas datas do período.'],
            'datas invertidas' => ['periodo=personalizado&de=2026-10-31&ate=2026-10-01', 'A data inicial é depois da final.'],
            'termina no futuro' => ['periodo=personalizado&de=2026-11-01&ate=2026-12-01', 'O período não pode terminar no futuro.'],
            'mais de 90 dias' => ['periodo=personalizado&de=2026-01-01&ate=2026-10-31', 'O período pode ter no máximo 90 dias.'],
            'array na url' => ['periodo[]=7', 'Período inválido.'],
        ];
    }

    public function test_filtro_de_tipo_e_totais_do_periodo(): void
    {
        [$helena, $marina] = [$this->usuarioComSaldo(0), $this->usuarioComSaldo(0)];
        $this->depositarEm($helena, '2026-11-10 15:00:00', '100,00');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:00:00', 'UTC'));
        app(Transferir::class)->handle($helena, $marina->email, Money::fromDecimalString('30,00'), (string) Str::uuid());
        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00:00', 'UTC'));

        $this->actingAs($helena)->get('/dashboard')
            ->assertSeeInOrder(['Entradas', 'R$ 100,00', 'Saídas', '-R$ 30,00', 'Movimentações', '2']);

        $this->actingAs($helena)->get('/dashboard?tipo=saidas')
            ->assertSee('Transferência enviada')
            ->assertDontSee('+R$ 100,00');

        $this->actingAs($helena)->get('/dashboard?tipo=entradas')
            ->assertSee('+R$ 100,00')
            ->assertDontSee('Transferência enviada');
    }

    public function test_extrato_mostra_com_quem_foi_cada_movimentacao(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $helena->forceFill(['name' => 'Helena Prado'])->save();
        $marina->forceFill(['name' => 'Marina Lopes'])->save();

        $transferencia = app(Transferir::class)->handle($helena, $marina->email, Money::fromDecimalString('30,00'), (string) Str::uuid());
        app(Estornar::class)->handle($transferencia, 'Recebi por engano', $marina);

        $this->actingAs($helena)->get('/dashboard')
            ->assertSeeInOrder(['Devolução recebida', 'De Marina Lopes', 'Transferência enviada', 'Para Marina Lopes']);

        $this->actingAs($marina->fresh())->get('/dashboard')
            ->assertSeeInOrder(['Devolução enviada', 'Para Helena Prado', 'Transferência recebida', 'De Helena Prado']);
    }

    public function test_paginacao_por_cursor_traz_o_resto_na_pagina_seguinte(): void
    {
        $helena = $this->usuarioComSaldo(0);
        for ($i = 1; $i <= 25; $i++) {
            $this->depositarEm($helena, '2026-11-12 15:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00', $i . ',00');
        }

        $primeira = $this->actingAs($helena)->get('/dashboard')->assertSee('+R$ 25,00')->assertDontSee('+R$ 5,00');
        preg_match('/href="([^"]+)" class="hover:text-brand">Mais antigas/', $primeira->getContent(), $link);

        $this->assertNotEmpty($link);
        $this->actingAs($helena)->get(html_entity_decode($link[1]))
            ->assertSee('+R$ 5,00')
            ->assertSee('+R$ 1,00')
            ->assertDontSee('+R$ 25,00');
    }

    #[DataProvider('cursoresForjados')]
    public function test_cursor_forjado_volta_para_a_primeira_pagina(string $cursor): void
    {
        $helena = $this->usuarioComSaldo(0);
        $this->depositarEm($helena, '2026-11-12 15:00:00', '7,00');

        $this->actingAs($helena)->get('/dashboard?cursor=' . $cursor)
            ->assertOk()
            ->assertSee('+R$ 7,00');
    }

    /** @return array<string, array{0: string}> */
    public static function cursoresForjados(): array
    {
        $codificar = function (array $dados): string {
            return rtrim(strtr(base64_encode((string) json_encode($dados)), '+/', '-_'), '=');
        };

        return [
            'sem a chave de direção' => [$codificar(['created_at' => '2026-11-12 15:00:00', 'id' => 1])],
            'data impossível' => [$codificar(['created_at' => '2026-02-31 10:00:00', 'id' => 1, '_pointsToNextItems' => true])],
            'texto na data' => [$codificar(['created_at' => "x' OR 1=1 --", 'id' => 1, '_pointsToNextItems' => true])],
            'id como texto' => [$codificar(['created_at' => '2026-11-12 15:00:00', 'id' => '1 OR 1=1', '_pointsToNextItems' => true])],
            'não é base64' => ['%%%'],
        ];
    }

    public function test_estorno_do_atendimento_mostra_quem_fez_e_saldo_negativo_em_vermelho(): void
    {
        $helena = $this->usuarioComSaldo(-18000);

        $this->actingAs($helena)->get('/dashboard')
            ->assertSee('Mais recentes primeiro')
            ->assertSee('Estorno de depósito')
            ->assertSee('Depósito em duplicidade')
            ->assertSee('font-semibold text-danger', false);
    }

    private function depositarEm(User $user, string $quandoUtc, string $valor): void
    {
        $agora = now();
        $this->travelTo(CarbonImmutable::parse($quandoUtc, 'UTC'));
        app(Depositar::class)->handle($user, Money::fromDecimalString($valor), (string) Str::uuid());
        $this->travelTo($agora);
    }
}
