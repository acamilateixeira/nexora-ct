<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Carteira\Depositar;
use App\Actions\Carteira\Estornar;
use App\Actions\Carteira\Transferir;
use App\Exceptions\Carteira\SaldoInsuficiente;
use App\Models\Operacao;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\ConfereLivroRazao;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class SimulacaoCentavosTest extends TestCase
{
    use RefreshDatabase;
    use ConfereLivroRazao;
    use PreparaCarteiras;

    public function test_dez_mais_vinte_centavos_da_trinta(): void
    {
        $this->assertNotSame(0.3, 0.1 + 0.2, 'com float a conta já erra');

        $helena = $this->usuarioComSaldo(0);
        $this->depositar($helena, '0,10');
        $this->depositar($helena, '0,20');

        $this->assertSame(30, $this->saldo($helena));
    }

    public function test_cem_depositos_de_um_centavo_dao_um_real(): void
    {
        $helena = $this->usuarioComSaldo(0);

        for ($i = 0; $i < 100; $i++) {
            $this->depositar($helena, '0,01');
        }

        $this->assertSame(100, $this->saldo($helena));
        $this->assertSame(100, $helena->carteira->lancamentos()->count());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_cem_reais_divididos_em_tres_sobra_um_centavo(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);

        for ($i = 0; $i < 3; $i++) {
            $this->transferir($helena, $marina, '33,33');
        }
        $this->assertSame(1, $this->saldo($helena));
        $this->assertSame(9999, $this->saldo($marina));

        try {
            $this->transferir($helena, $marina, '0,02');
            $this->fail('Com R$ 0,01 não dá para transferir R$ 0,02.');
        } catch (SaldoInsuficiente $e) {
            $this->assertSame(1, $this->saldo($helena));
        }

        $this->transferir($helena, $marina, '0,01');
        $this->assertSame(0, $this->saldo($helena));
        $this->assertSame(10000, $this->saldo($marina));
    }

    public function test_centavos_indo_e_voltando_nao_somem_nem_aparecem(): void
    {
        $helena = $this->usuarioComSaldo(50000);
        $marina = $this->usuarioComSaldo(50000);
        mt_srand(2026);

        for ($i = 0; $i < 300; $i++) {
            [$de, $para] = $i % 2 === 0 ? [$helena, $marina] : [$marina, $helena];
            $centavos = mt_rand(1, 999);
            if ($this->saldo($de) >= $centavos) {
                $this->transferir($de, $para, Money::ofCents($centavos)->toDecimalString());
            }
        }

        $this->assertSame(100000, $this->saldo($helena) + $this->saldo($marina));
        $this->assertSaldosBatemComLancamentos();
        $this->artisan('carteira:conciliar')->assertExitCode(0);
    }

    #[DataProvider('formatos')]
    public function test_le_o_valor_em_qualquer_formato_comum(string $digitado, int $centavos, string $naTela): void
    {
        $helena = $this->usuarioComSaldo(0);

        $this->actingAs($helena)
            ->post('/depositos', ['valor' => $digitado, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHas('status', "Depósito de {$naTela} realizado.");

        $this->assertSame($centavos, $this->saldo($helena));
        $this->actingAs($helena)->get('/dashboard')->assertSee($naTela);
    }

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function formatos(): array
    {
        return [
            'um centavo' => ['0,01', 1, 'R$ 0,01'],
            'meio real com uma casa' => ['0,5', 50, 'R$ 0,50'],
            'ponto como decimal' => ['10.5', 1050, 'R$ 10,50'],
            'milhar e centavos' => ['1.234,56', 123456, 'R$ 1.234,56'],
            'maior valor aceito' => ['999.999.999,99', 99999999999, 'R$ 999.999.999,99'],
        ];
    }

    public function test_valor_acima_do_teto_e_recusado(): void
    {
        $helena = $this->usuarioComSaldo(0);

        $this->actingAs($helena)
            ->post('/depositos', ['valor' => '1.000.000.000,00', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('valor');

        $this->assertSame(0, $this->saldo($helena));
    }

    public function test_estorno_de_um_centavo_que_ja_foi_gasto_deixa_menos_um_centavo(): void
    {
        $helena = $this->usuarioComSaldo(0);
        $marina = $this->usuarioComSaldo(0);
        $deposito = $this->depositar($helena, '0,01');
        $this->transferir($helena, $marina, '0,01');

        app(Estornar::class)->handle($deposito, 'Depósito em duplicidade', null, 'Atendimento');

        $this->assertSame(-1, $this->saldo($helena));
        $this->actingAs($helena)->get('/dashboard')->assertSee('-R$ 0,01');

        $this->depositar($helena, '0,02');
        $this->assertSame(1, $this->saldo($helena));
        $this->assertSaldosBatemComLancamentos();
    }

    private function depositar(User $user, string $valor): Operacao
    {
        return app(Depositar::class)->handle($user, Money::fromDecimalString($valor), (string) Str::uuid());
    }

    private function transferir(User $de, User $para, string $valor): void
    {
        app(Transferir::class)->handle($de, $para->email, Money::fromDecimalString($valor), (string) Str::uuid());
    }

    private function saldo(User $user): int
    {
        return $user->carteira()->firstOrFail()->saldo->cents();
    }
}
