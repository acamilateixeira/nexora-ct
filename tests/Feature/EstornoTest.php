<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Carteira\DevolverTransferencia;
use App\Actions\Carteira\Estornar;
use App\Actions\Carteira\SolicitarEstorno;
use App\Models\Operacao;
use App\Models\SolicitacaoEstorno;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\ConfereLivroRazao;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class EstornoTest extends TestCase
{
    use RefreshDatabase;
    use ConfereLivroRazao;
    use PreparaCarteiras;

    public function test_deposito_nao_tem_estorno_pela_tela(): void
    {
        $helena = $this->usuarioComSaldo(0);
        $deposito = $this->depositar($helena, '100,00');

        $this->actingAs($helena)->get("/operacoes/{$deposito->uuid}")
            ->assertOk()
            ->assertSee('Algum problema com este depósito?')
            ->assertSee(config('carteira.atendimento.telefone'))
            ->assertDontSee('Devolver')
            ->assertDontSee('Contestar transferência');

        $this->actingAs($helena)->post("/operacoes/{$deposito->uuid}/estorno", ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($helena)->post("/operacoes/{$deposito->uuid}/solicitacao-estorno", ['motivo' => 'x'])->assertForbidden();
        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
    }

    public function test_quem_recebeu_devolve_a_transferencia(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($marina)
            ->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'Recebi por engano'])
            ->assertRedirect("/operacoes/{$transferencia->uuid}")
            ->assertSessionHas('status', 'Valor devolvido para ' . rtrim($helena->nomeAbreviado(), '.') . '.');

        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(0, $marina->carteira->fresh()->saldo->cents());
        $this->assertSame(Operacao::STATUS_ESTORNADA, $transferencia->fresh()->status);
        $this->assertSame('Recebi por engano', $transferencia->fresh()->estorno->motivo);
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_devolucao_acontece_mesmo_sem_saldo_e_quem_enviou_nao_sai_no_prejuizo(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');
        $this->transferir($marina, $rafael, '30,00');

        $this->actingAs($marina)
            ->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'Recebi por engano'])
            ->assertSessionHasNoErrors();

        $this->assertSame(-3000, $marina->carteira->fresh()->saldo->cents());
        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(Operacao::STATUS_ESTORNADA, $transferencia->fresh()->status);
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_quem_enviou_nao_tira_dinheiro_direto_de_quem_recebeu(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($helena)->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'x'])->assertForbidden();

        $this->assertSame(3000, $marina->carteira->fresh()->saldo->cents());
    }

    public function test_quem_enviou_contesta_e_o_atendimento_aprova(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($helena)
            ->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'Enviei o valor errado'])
            ->assertSessionHas('status', 'Contestação enviada para análise do atendimento.');
        $this->assertSame(3000, $marina->carteira->fresh()->saldo->cents());

        $this->assertSame(3000, $marina->carteira->fresh()->bloqueado->cents());
        $this->actingAs($marina)->get('/dashboard')->assertSee('Bloqueado para análise: R$ 30,00')->assertSee('bloqueado para análise');
        $this->actingAs($marina)->get("/operacoes/{$transferencia->uuid}")->assertSee('Valor bloqueado para análise.')->assertDontSee('Enviei o valor errado');
        $this->actingAs($helena)->get('/dashboard')->assertSee('contestação em análise');
        $this->actingAs($helena)->get("/operacoes/{$transferencia->uuid}")->assertSee('Sua contestação está em análise.');

        $this->artisan('carteira:estornar', ['uuid' => $transferencia->uuid, '--motivo' => 'Contestação aprovada', '--operador' => 'Atendimento'])->assertExitCode(0);

        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(SolicitacaoEstorno::STATUS_ACEITA, $transferencia->solicitacaoEstorno()->firstOrFail()->status);
        $this->assertSame(0, $marina->carteira->fresh()->bloqueado->cents());
        $this->actingAs($helena)->get('/dashboard')->assertSeeInOrder(['Estorno recebido', 'Contestação aprovada']);
        $this->actingAs($marina)->get('/dashboard')->assertSeeInOrder(['Estorno debitado', 'Contestação aprovada']);
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_atendimento_nega_e_o_pedido_nao_se_repete(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');
        $this->actingAs($helena)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'Enviei o valor errado']);

        $this->artisan('carteira:negar-estorno', ['uuid' => $transferencia->uuid, '--motivo' => 'Valor conferido com o recebedor', '--operador' => 'Atendimento'])
            ->assertExitCode(0);

        $solicitacao = $transferencia->solicitacaoEstorno()->firstOrFail();
        $this->assertSame(SolicitacaoEstorno::STATUS_RECUSADA, $solicitacao->status);
        $this->assertSame(3000, $marina->carteira->fresh()->saldo->cents());
        $this->assertSame(0, $marina->carteira->fresh()->bloqueado->cents());

        $this->actingAs($helena)->get("/operacoes/{$transferencia->uuid}")
            ->assertSee('Contestação não aprovada em')
            ->assertSee('Valor conferido com o recebedor');
        $this->actingAs($helena)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'De novo'])->assertForbidden();
        $this->artisan('carteira:negar-estorno', ['uuid' => $transferencia->uuid, '--motivo' => 'x', '--operador' => 'x'])->assertExitCode(1);
    }

    public function test_a_acao_de_contestar_recusa_quem_nao_enviou_mesmo_sem_passar_pela_rota(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        foreach ([$marina, $rafael] as $quem) {
            try {
                app(SolicitarEstorno::class)->handle($transferencia, $quem, 'tentativa');
                $this->fail('Só quem enviou pode contestar.');
            } catch (AuthorizationException $e) {
                $this->assertNull($transferencia->solicitacaoEstorno()->first());
                $this->assertTrue($marina->carteira->fresh()->bloqueado->isZero());
            }
        }
    }

    public function test_a_acao_de_devolver_recusa_quem_nao_recebeu_mesmo_sem_passar_pela_rota(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        foreach ([$helena, $rafael] as $quem) {
            try {
                app(DevolverTransferencia::class)->handle($transferencia, $quem, 'tentativa');
                $this->fail('Só quem recebeu pode devolver.');
            } catch (AuthorizationException $e) {
                $this->assertSame(Operacao::STATUS_CONCLUIDA, $transferencia->fresh()->status);
                $this->assertSame(3000, $marina->carteira->fresh()->saldo->cents());
            }
        }
    }

    public function test_so_quem_enviou_contesta(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($marina)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($rafael)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'x'])->assertForbidden();
        $this->assertNull($transferencia->solicitacaoEstorno()->first());
    }

    public function test_quem_nao_participa_nao_ve_nem_estorna(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($rafael)->get("/operacoes/{$transferencia->uuid}")->assertForbidden();
        $this->actingAs($rafael)->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'x'])->assertForbidden();

        $this->assertSame(Operacao::STATUS_CONCLUIDA, $transferencia->fresh()->status);
    }

    public function test_transferencia_nao_e_devolvida_duas_vezes(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(5000);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($marina)->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'Recebi por engano']);
        $this->actingAs($marina)->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'De novo'])->assertForbidden();

        $this->assertSame(5000, $marina->carteira->fresh()->saldo->cents());
        $this->assertSame(1, Operacao::where('tipo', Operacao::TIPO_ESTORNO)->count());
    }

    public function test_estorno_nao_se_estorna(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');
        $this->actingAs($marina)->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => 'Recebi por engano']);
        $estorno = $transferencia->fresh()->estorno;

        $this->actingAs($helena)->post("/operacoes/{$estorno->uuid}/estorno", ['motivo' => 'x'])->assertForbidden();
        $this->actingAs($helena)->post("/operacoes/{$estorno->uuid}/solicitacao-estorno", ['motivo' => 'x'])->assertForbidden();
    }

    public function test_motivo_e_obrigatorio_na_devolucao_e_no_pedido(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');

        $this->actingAs($marina)->post("/operacoes/{$transferencia->uuid}/estorno", ['motivo' => ''])
            ->assertSessionHasErrors(['motivo' => 'Conte o motivo do estorno.']);
        $this->actingAs($helena)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(Operacao::STATUS_CONCLUIDA, $transferencia->fresh()->status);
    }

    public function test_tela_mostra_a_acao_certa_para_cada_lado(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '50,00');

        $this->actingAs($helena)->get("/operacoes/{$transferencia->uuid}")
            ->assertSee($helena->email)
            ->assertSee($marina->email)
            ->assertSee('Contestar transferência')
            ->assertDontSee('Devolver R$');
        $this->actingAs($marina)->get("/operacoes/{$transferencia->uuid}")
            ->assertSee('Devolver R$ 50,00')
            ->assertDontSee('Contestar transferência');
    }

    public function test_atendimento_estorna_deposito_ja_gasto_e_o_deposito_seguinte_cobre(): void
    {
        $helena = $this->usuarioComSaldo(0);
        $marina = $this->usuarioComSaldo(0);
        $deposito = $this->depositar($helena, '50,00');
        $this->transferir($helena, $marina, '50,00');

        app(Estornar::class)->handle($deposito, 'Depósito em duplicidade', null, 'Equipe de atendimento');
        $this->assertSame(-5000, $helena->carteira->fresh()->saldo->cents());

        $this->depositar($helena, '80,00');
        $this->assertSame(3000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_valor_bloqueado_nao_pode_ser_transferido(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');
        $this->actingAs($helena)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'Golpe']);

        $this->actingAs($marina)
            ->post('/transferencias', ['email' => $rafael->email, 'valor' => '10,00', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors(['valor' => 'Saldo insuficiente para essa transferência.']);

        $this->assertSame(3000, $marina->carteira->fresh()->saldo->cents());
        $this->actingAs($marina)->get('/transferencias/nova')->assertSee('R$ 0,00');
    }

    public function test_bloqueio_vai_ate_o_que_ainda_esta_disponivel(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $transferencia = $this->transferir($helena, $marina, '30,00');
        $this->transferir($marina, $rafael, '20,00');

        $this->actingAs($helena)->post("/operacoes/{$transferencia->uuid}/solicitacao-estorno", ['motivo' => 'Golpe']);

        $this->assertSame(1000, $marina->carteira->fresh()->bloqueado->cents());
        $this->assertSame(1000, $transferencia->solicitacaoEstorno()->firstOrFail()->valor_bloqueado);

        $this->artisan('carteira:estornar', ['uuid' => $transferencia->uuid, '--motivo' => 'Golpe confirmado', '--operador' => 'Atendimento']);
        $this->assertSame(-2000, $marina->carteira->fresh()->saldo->cents());
        $this->assertSame(0, $marina->carteira->fresh()->bloqueado->cents());
        $this->assertSaldosBatemComLancamentos();
    }

    private function depositar(User $user, string $valor): Operacao
    {
        $this->actingAs($user)->post('/depositos', ['valor' => $valor, 'idempotency_key' => (string) Str::uuid()]);

        return Operacao::where('tipo', Operacao::TIPO_DEPOSITO)->orderByDesc('id')->firstOrFail();
    }

    private function transferir(User $de, User $para, string $valor): Operacao
    {
        $this->actingAs($de)->post('/transferencias', [
            'email' => $para->email,
            'valor' => $valor,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return Operacao::where('tipo', Operacao::TIPO_TRANSFERENCIA)->orderByDesc('id')->firstOrFail();
    }
}
