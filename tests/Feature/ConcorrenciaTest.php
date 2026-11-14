<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Operacao;
use App\Models\SolicitacaoEstorno;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\ConfereLivroRazao;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class ConcorrenciaTest extends TestCase
{
    use DatabaseMigrations;
    use ConfereLivroRazao;
    use PreparaCarteiras;

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
    }

    public function test_cinco_transferencias_simultaneas_nao_gastam_o_mesmo_saldo(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $destinos = [];
        for ($i = 0; $i < 5; $i++) {
            $destinos[] = $this->usuarioComSaldo(0);
        }

        $saidas = $this->emParalelo(array_map(function (User $destino) use ($helena) {
            return ['transferir', $helena->id, $destino->email, '30,00', (string) Str::uuid()];
        }, $destinos));

        $this->assertCount(3, array_filter($saidas, [$this, 'deuCerto']), implode("\n", $saidas));
        $this->assertCount(2, array_keys($saidas, 'recusado saldo_insuficiente', true));
        $this->assertSame(1000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_a_para_b_e_b_para_a_ao_mesmo_tempo_nao_travam(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(10000);

        $comandos = [];
        for ($i = 0; $i < 6; $i++) {
            [$de, $para] = $i % 2 === 0 ? [$helena, $marina] : [$marina, $helena];
            $comandos[] = ['transferir', $de->id, $para->email, '10,01', (string) Str::uuid()];
        }

        $saidas = $this->emParalelo($comandos);

        $this->assertCount(6, array_filter($saidas, [$this, 'deuCerto']), implode("\n", $saidas));
        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(10000, $marina->carteira->fresh()->saldo->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_mesma_key_ao_mesmo_tempo_gera_uma_operacao_so(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $idempotencyKey = (string) Str::uuid();

        $saidas = $this->emParalelo(array_fill(0, 4, ['transferir', $helena->id, $marina->email, '25,00', $idempotencyKey]));

        $this->assertCount(1, array_unique($saidas), 'todos recebem a mesma operação: ' . implode("\n", $saidas));
        $this->assertTrue($this->deuCerto($saidas[0]));
        $this->assertSame(1, Operacao::where('idempotency_key', $idempotencyKey)->count());
        $this->assertSame(7500, $helena->carteira->fresh()->saldo->cents());
    }

    public function test_dois_estornos_ao_mesmo_tempo_so_um_passa(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        [$saida] = $this->emParalelo([['transferir', $helena->id, $marina->email, '40,00', (string) Str::uuid()]]);
        $uuid = substr($saida, 3);

        $saidas = $this->emParalelo([
            ['estornar', $uuid, $helena->id],
            ['estornar', $uuid, $marina->id],
            ['estornar', $uuid, $helena->id],
        ]);

        $this->assertCount(1, array_filter($saidas, [$this, 'deuCerto']), implode("\n", $saidas));
        $this->assertCount(2, array_keys($saidas, 'recusado operacao_ja_estornada', true));
        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_contestar_e_devolver_ao_mesmo_tempo_devolve_uma_vez_e_nao_deixa_bloqueio(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        [$saida] = $this->emParalelo([['transferir', $helena->id, $marina->email, '40,00', (string) Str::uuid()]]);
        $uuid = substr($saida, 3);

        [$contestacao, $devolucao] = $this->emParalelo([
            ['contestar', $uuid, $helena->id],
            ['estornar', $uuid, $marina->id],
        ]);

        $this->assertTrue($this->deuCerto($devolucao), $devolucao);
        $this->assertTrue($this->deuCerto($contestacao) || $contestacao === 'recusado operacao_ja_estornada', $contestacao);

        $transferencia = Operacao::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue($transferencia->estaEstornada());
        $this->assertSame(0, SolicitacaoEstorno::where('operacao_id', $transferencia->id)->where('status', SolicitacaoEstorno::STATUS_PENDENTE)->count());
        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertTrue($marina->carteira->fresh()->saldo->isZero());
        $this->assertTrue($marina->carteira->fresh()->bloqueado->isZero());
        $this->assertSaldosBatemComLancamentos();
    }

    /**
     * @param array<int, array<int, mixed>> $comandos
     * @return array<int, string>
     */
    private function emParalelo(array $comandos): array
    {
        $comecarEm = (string) (microtime(true) + 3);

        $processes = array_map(function (array $comando) use ($comecarEm) {
            $process = new Process(
                array_merge([PHP_BINARY, base_path('tests/Concorrencia/worker.php'), $comecarEm], array_map('strval', $comando)),
                base_path(),
                ['APP_ENV' => 'testing']
            );
            $process->setTimeout(60);
            $process->start();

            return $process;
        }, $comandos);

        return array_map(function (Process $process) {
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());

            return trim($process->getOutput());
        }, $processes);
    }

    private function deuCerto(string $saida): bool
    {
        return strpos($saida, 'ok ') === 0;
    }
}
