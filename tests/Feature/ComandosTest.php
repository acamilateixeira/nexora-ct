<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Operacao;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class ComandosTest extends TestCase
{
    use RefreshDatabase;
    use PreparaCarteiras;

    public function test_conciliar_sem_divergencia_termina_com_sucesso(): void
    {
        $this->usuarioComSaldo(5000);
        $this->usuarioComSaldo(-300);

        $this->artisan('carteira:conciliar')
            ->expectsOutput('Nenhuma divergência.')
            ->assertExitCode(0);
    }

    public function test_conciliar_detecta_saldo_adulterado_e_nao_corrige(): void
    {
        $helena = $this->usuarioComSaldo(5000);

        DB::statement('SET @lancamento_na_carteira = ?', [$helena->carteira->id]);
        DB::table('carteiras')->where('id', $helena->carteira->id)->update(['saldo' => 999999]);
        DB::statement('SET @lancamento_na_carteira = NULL');
        Log::spy();

        $this->artisan('carteira:conciliar')->assertExitCode(1);

        Log::shouldHaveReceived('critical')->withArgs(function ($mensagem, $context) use ($helena) {
            return $mensagem === 'carteira.saldo_divergente'
                && $context['carteira_id'] === $helena->carteira->id
                && $context['saldo'] === 999999
                && $context['soma_lancamentos'] === 5000;
        });
        $this->assertSame(999999, $helena->carteira->fresh()->saldo->cents());
    }

    public function test_conciliar_detecta_operacao_sem_lancamentos(): void
    {
        $helena = $this->usuarioComSaldo(0);
        $operacao = Operacao::create([
            'tipo' => Operacao::TIPO_DEPOSITO,
            'status' => Operacao::STATUS_CONCLUIDA,
            'valor' => Money::ofCents(100),
            'carteira_destino_id' => $helena->carteira->id,
            'criada_por' => $helena->id,
        ]);
        Log::spy();

        $this->artisan('carteira:conciliar')->assertExitCode(1);

        Log::shouldHaveReceived('critical')->withArgs(function ($mensagem, $context) use ($operacao) {
            return $mensagem === 'carteira.operacao_malformada' && $context['operacao'] === $operacao->uuid;
        });
    }

    public function test_operador_estorna_com_motivo(): void
    {
        $helena = $this->usuarioComSaldo(5000);
        $deposito = Operacao::firstOrFail();

        $this->artisan('carteira:estornar', [
            'uuid' => $deposito->uuid,
            '--motivo' => 'Depósito duplicado no banco',
            '--operador' => 'Camila (suporte)',
        ])->assertExitCode(0);

        $this->assertTrue($helena->carteira->fresh()->saldo->isZero());
        $estorno = $deposito->fresh()->estorno;
        $this->assertNull($estorno->criada_por);
        $this->assertSame('Camila (suporte)', $estorno->operador);
        $this->assertSame('Depósito duplicado no banco', $estorno->motivo);
    }

    public function test_operador_precisa_informar_o_motivo(): void
    {
        $this->usuarioComSaldo(5000);
        $deposito = Operacao::firstOrFail();

        $this->artisan('carteira:estornar', ['uuid' => $deposito->uuid, '--operador' => 'Camila'])->assertExitCode(1);

        $this->assertSame(Operacao::STATUS_CONCLUIDA, $deposito->fresh()->status);
    }

    public function test_operador_precisa_se_identificar(): void
    {
        $this->usuarioComSaldo(5000);
        $deposito = Operacao::firstOrFail();

        $this->artisan('carteira:estornar', ['uuid' => $deposito->uuid, '--motivo' => 'x'])->assertExitCode(1);

        $this->assertSame(Operacao::STATUS_CONCLUIDA, $deposito->fresh()->status);
    }

    public function test_estornar_operacao_inexistente_falha(): void
    {
        $this->artisan('carteira:estornar', ['uuid' => '00000000-0000-0000-0000-000000000000', '--motivo' => 'x', '--operador' => 'Camila'])
            ->assertExitCode(1);
    }

    public function test_estornar_duas_vezes_pelo_comando_falha_na_segunda(): void
    {
        $this->usuarioComSaldo(5000);
        $deposito = Operacao::firstOrFail();

        $this->artisan('carteira:estornar', ['uuid' => $deposito->uuid, '--motivo' => 'primeira', '--operador' => 'Camila'])->assertExitCode(0);
        $this->artisan('carteira:estornar', ['uuid' => $deposito->uuid, '--motivo' => 'segunda', '--operador' => 'Camila'])
            ->expectsOutput('Esta operação já foi estornada.')
            ->assertExitCode(1);
    }
}
