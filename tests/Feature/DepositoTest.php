<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Carteira\Depositar;
use App\Actions\Carteira\Idempotency;
use App\Exceptions\Carteira\IdempotencyKeyReused;
use App\Models\Operacao;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ConfereLivroRazao;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class DepositoTest extends TestCase
{
    use RefreshDatabase;
    use ConfereLivroRazao;
    use PreparaCarteiras;

    public function test_tela_de_deposito_abre_com_uma_key_nova(): void
    {
        $this->actingAs($this->usuarioComSaldo(0))
            ->get('/depositos/novo')
            ->assertOk()
            ->assertSee('name="idempotency_key"', false);
    }

    public function test_deposito_soma_ao_saldo_e_gera_um_lancamento(): void
    {
        $user = $this->usuarioComSaldo(1000);

        $this->actingAs($user)
            ->post('/depositos', $this->dados('25,50'))
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status');

        $this->assertSame(3550, $user->carteira->fresh()->saldo->cents());

        $operacao = Operacao::orderByDesc('id')->firstOrFail();
        $this->assertSame(Operacao::TIPO_DEPOSITO, $operacao->tipo);
        $this->assertSame(2550, $operacao->valor->cents());
        $this->assertSame($user->id, $operacao->criada_por);
        $this->assertSame(1, $operacao->lancamentos()->count());
        $this->assertSame(3550, $operacao->lancamentos()->first()->saldo_apos->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_deposito_em_saldo_negativo_soma_ao_valor(): void
    {
        $user = $this->usuarioComSaldo(-5000);

        $this->actingAs($user)->post('/depositos', $this->dados('100,00'));

        $this->assertSame(5000, $user->carteira->fresh()->saldo->cents());
    }

    #[DataProvider('valoresInvalidos')]
    public function test_valor_invalido_e_recusado_e_nada_e_gravado(string $valor): void
    {
        $user = $this->usuarioComSaldo(0);

        $this->actingAs($user)
            ->from('/depositos/novo')
            ->post('/depositos', $this->dados($valor))
            ->assertRedirect('/depositos/novo')
            ->assertSessionHasErrors('valor');

        $this->assertSame(0, Operacao::count());
        $this->assertTrue($user->carteira->fresh()->saldo->isZero());
    }

    /** @return array<string, array{0: string}> */
    public static function valoresInvalidos(): array
    {
        return [
            'vazio' => [''],
            'texto' => ['dez reais'],
            'zero' => ['0,00'],
            'negativo' => ['-10,00'],
            'três casas' => ['10,505'],
        ];
    }

    public function test_mesma_key_enviada_duas_vezes_deposita_uma_vez_so(): void
    {
        $user = $this->usuarioComSaldo(0);
        $dados = $this->dados('10,00');

        $this->actingAs($user)->post('/depositos', $dados);
        $this->actingAs($user)->post('/depositos', $dados)
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', 'Esse depósito já tinha sido registrado. Nenhum valor novo foi lançado.');

        $this->assertSame(1, Operacao::count());
        $this->assertSame(1000, $user->carteira->fresh()->saldo->cents());
    }

    public function test_mesma_key_com_outro_valor_e_recusada(): void
    {
        $user = $this->usuarioComSaldo(0);
        $idempotencyKey = (string) Str::uuid();

        $this->actingAs($user)->post('/depositos', $this->dados('10,00', $idempotencyKey));
        $this->actingAs($user)
            ->post('/depositos', $this->dados('99,00', $idempotencyKey))
            ->assertSessionHasErrors('valor');

        $this->assertSame(1, Operacao::count());
        $this->assertSame(1000, $user->carteira->fresh()->saldo->cents());
    }

    public function test_quando_o_unique_barra_a_key_devolve_a_operacao_existente(): void
    {
        $user = $this->usuarioComSaldo(0);
        $idempotencyKey = (string) Str::uuid();
        $this->actingAs($user)->post('/depositos', $this->dados('10,00', $idempotencyKey));
        $primeira = Operacao::where('idempotency_key', $idempotencyKey)->firstOrFail();

        $this->partialMock(Idempotency::class, function ($mock) {
            $mock->shouldReceive('findExisting')->andReturn(null);
        });

        $repetida = app(Depositar::class)->handle($user, Money::ofCents(1000), $idempotencyKey);

        $this->assertTrue($repetida->is($primeira));
        $this->assertSame(1000, $user->carteira->fresh()->saldo->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_quando_o_unique_barra_a_key_com_outro_valor_recusa(): void
    {
        $user = $this->usuarioComSaldo(0);
        $idempotencyKey = (string) Str::uuid();
        $this->actingAs($user)->post('/depositos', $this->dados('10,00', $idempotencyKey));

        $this->partialMock(Idempotency::class, function ($mock) {
            $mock->shouldReceive('findExisting')->andReturn(null);
        });

        $this->expectException(IdempotencyKeyReused::class);

        app(Depositar::class)->handle($user, Money::ofCents(9900), $idempotencyKey);
    }

    public function test_key_precisa_ser_um_uuid(): void
    {
        $this->actingAs($this->usuarioComSaldo(0))
            ->post('/depositos', $this->dados('10,00', 'qualquer-coisa'))
            ->assertSessionHasErrors('idempotency_key');
    }

    public function test_visitante_nao_deposita(): void
    {
        $this->post('/depositos', $this->dados('10,00'))->assertRedirect('/login');

        $this->assertSame(0, Operacao::count());
    }

    /** @return array<string, string> */
    private function dados(string $valor, ?string $idempotencyKey = null): array
    {
        return ['valor' => $valor, 'idempotency_key' => $idempotencyKey ?? (string) Str::uuid()];
    }

    public function test_revisao_mostra_valor_e_saldo_sem_lancar(): void
    {
        $user = $this->usuarioComSaldo(-5000);
        $dados = $this->dados('10,00');

        $this->actingAs($user)->post('/depositos/revisar', $dados)
            ->assertOk()
            ->assertSee('R$ 10,00')
            ->assertSee('-R$ 40,00')
            ->assertSee('value="' . $dados['idempotency_key'] . '"', false);

        $this->assertSame(-5000, $user->carteira->fresh()->saldo->cents());
    }

    public function test_revisao_com_valor_invalido_volta_para_o_formulario(): void
    {
        $user = $this->usuarioComSaldo(0);

        $this->actingAs($user)->from('/depositos/novo')
            ->post('/depositos/revisar', $this->dados('0,00'))
            ->assertRedirect('/depositos/novo')
            ->assertSessionHasErrors('valor');
    }
}
