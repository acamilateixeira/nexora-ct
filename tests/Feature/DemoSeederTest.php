<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\SolicitacaoEstorno;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ConfereLivroRazao;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;
    use ConfereLivroRazao;

    public function test_cria_as_tres_contas_com_cada_situacao_e_o_rafael_negativo(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(320000, $this->saldo('helena@exemplo.com'));
        $this->assertSame(150000, $this->saldo('marina@exemplo.com'));
        $this->assertSame(-15000, $this->saldo('rafael@exemplo.com'));
        $this->assertSame(3, SolicitacaoEstorno::where('status', SolicitacaoEstorno::STATUS_PENDENTE)->count());
        $this->assertSame(3, SolicitacaoEstorno::where('status', SolicitacaoEstorno::STATUS_ACEITA)->count());
        $this->assertSame(3, SolicitacaoEstorno::where('status', SolicitacaoEstorno::STATUS_RECUSADA)->count());
        $this->assertSaldosBatemComLancamentos();
        $this->artisan('carteira:conciliar')->assertExitCode(0);
    }

    public function test_no_extrato_cada_saldo_apos_continua_o_anterior(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (Carteira::all() as $carteira) {
            $saldo = 0;
            foreach ($carteira->lancamentos()->orderBy('created_at')->orderBy('id')->get() as $lancamento) {
                $saldo += $lancamento->valor->cents();
                $this->assertSame($saldo, $lancamento->saldo_apos->cents(), "lançamento {$lancamento->id} da carteira {$carteira->id}");
            }
        }
    }

    public function test_rodar_de_novo_nao_duplica_nada(): void
    {
        $this->seed(DemoSeeder::class);
        $operacoes = Operacao::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($operacoes, Operacao::count());
        $this->assertSame(3, User::count());
    }

    private function saldo(string $email): int
    {
        return User::where('email', $email)->firstOrFail()->carteira->saldo->cents();
    }
}
