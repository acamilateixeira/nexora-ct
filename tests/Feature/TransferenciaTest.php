<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Lancamento;
use App\Models\Operacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\ConfereLivroRazao;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class TransferenciaTest extends TestCase
{
    use RefreshDatabase;
    use ConfereLivroRazao;
    use PreparaCarteiras;

    public function test_transferencia_debita_de_um_lado_e_credita_do_outro(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(500);

        $resposta = $this->actingAs($helena)->post('/transferencias', $this->dados($marina->email, '30,00'));

        $this->assertSame(7000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(3500, $marina->carteira->fresh()->saldo->cents());

        $operacao = Operacao::orderByDesc('id')->firstOrFail();
        $resposta->assertRedirect(route('operacoes.show', $operacao))->assertSessionHas('status');
        $this->assertSame(Operacao::TIPO_TRANSFERENCIA, $operacao->tipo);
        $this->assertSame($helena->carteira->id, $operacao->carteira_origem_id);
        $this->assertSame($marina->carteira->id, $operacao->carteira_destino_id);
        $this->assertSame([-3000, 3000], $operacao->lancamentos()->orderBy('valor')->get()->map->valor->map->cents()->all());
        $this->assertSaldosBatemComLancamentos();
    }

    public function test_pode_transferir_todo_o_saldo(): void
    {
        $helena = $this->usuarioComSaldo(1000);
        $marina = $this->usuarioComSaldo(0);

        $this->actingAs($helena)->post('/transferencias', $this->dados($marina->email, '10,00'));

        $this->assertTrue($helena->carteira->fresh()->saldo->isZero());
    }

    public function test_sem_saldo_a_transferencia_e_recusada_e_nada_muda(): void
    {
        $helena = $this->usuarioComSaldo(1000);
        $marina = $this->usuarioComSaldo(0);

        $this->actingAs($helena)
            ->post('/transferencias', $this->dados($marina->email, '10,01'))
            ->assertSessionHasErrors(['valor' => 'Saldo insuficiente para essa transferência.']);

        $this->assertSame(1000, $helena->carteira->fresh()->saldo->cents());
        $this->assertTrue($marina->carteira->fresh()->saldo->isZero());
        $this->assertSame(0, Operacao::where('tipo', Operacao::TIPO_TRANSFERENCIA)->count());
    }

    public function test_com_saldo_negativo_nao_transfere(): void
    {
        $helena = $this->usuarioComSaldo(-100);
        $marina = $this->usuarioComSaldo(0);

        $this->actingAs($helena)
            ->post('/transferencias', $this->dados($marina->email, '0,01'))
            ->assertSessionHasErrors('valor');
    }

    public function test_nao_transfere_para_si_mesmo(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->post('/transferencias', $this->dados($helena->email, '1,00'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1000, $helena->carteira->fresh()->saldo->cents());
    }

    public function test_destinatario_precisa_existir(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->post('/transferencias', $this->dados('ninguem@example.com', '1,00'))
            ->assertSessionHasErrors('email');
    }

    public function test_destinatario_sem_carteira_e_tratado_como_inexistente(): void
    {
        $helena = $this->usuarioComSaldo(1000);
        $semCarteira = User::factory()->create();

        $this->actingAs($helena)
            ->post('/transferencias', $this->dados($semCarteira->email, '1,00'))
            ->assertSessionHasErrors(['email' => 'Não encontramos uma conta com esse e-mail. Confira se está escrito certo.']);

        $this->assertSame(1000, $helena->carteira->fresh()->saldo->cents());
    }

    public function test_email_com_quebra_de_linha_e_recusado(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->post('/transferencias', $this->dados("helena(\r\n x)@example.com", '1,00'))
            ->assertSessionHasErrors('email');
    }

    public function test_revisao_exibe_destinatario_e_nao_altera_saldo(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $marina->forceFill(['name' => 'Marina Prado Lopes'])->save();
        $dados = $this->dados($marina->email, '30,00');
        $operacoesAntes = Operacao::count();

        $this->actingAs($helena)
            ->post('/transferencias/revisar', $dados)
            ->assertOk()
            ->assertSee('Marina L.')
            ->assertDontSee('Prado')
            ->assertSee('R$ 30,00')
            ->assertSee('value="' . $dados['idempotency_key'] . '"', false);

        $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame($operacoesAntes, Operacao::count());
    }

    public function test_revisao_com_destinatario_inexistente_retorna_erro(): void
    {
        $helena = $this->usuarioComSaldo(10000);

        $this->actingAs($helena)
            ->from('/transferencias/nova')
            ->post('/transferencias/revisar', $this->dados('ninguem@example.com', '10,00'))
            ->assertRedirect('/transferencias/nova')
            ->assertSessionHasErrors('email');
    }

    public function test_revisao_com_valor_zero_retorna_erro(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);

        $this->actingAs($helena)
            ->from('/transferencias/nova')
            ->post('/transferencias/revisar', $this->dados($marina->email, '0,00'))
            ->assertRedirect('/transferencias/nova')
            ->assertSessionHasErrors('valor');
    }

    public function test_mesma_key_enviada_duas_vezes_transfere_uma_vez_so(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $dados = $this->dados($marina->email, '10,00');

        $primeira = $this->actingAs($helena)->post('/transferencias', $dados)
            ->assertSessionHas('status', 'Transferência de R$ 10,00 realizada.');
        $segunda = $this->actingAs($helena)->post('/transferencias', $dados)
            ->assertSessionHas('status', 'Essa transferência já tinha sido realizada. Nenhum valor novo foi enviado.');

        $this->assertSame($primeira->headers->get('Location'), $segunda->headers->get('Location'));

        $this->assertSame(9000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(1000, $marina->carteira->fresh()->saldo->cents());
    }

    public function test_mesma_key_para_outro_destinatario_e_recusada(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $rafael = $this->usuarioComSaldo(0);
        $idempotencyKey = (string) Str::uuid();

        $this->actingAs($helena)->post('/transferencias', $this->dados($marina->email, '10,00', $idempotencyKey));
        $this->actingAs($helena)
            ->post('/transferencias', $this->dados($rafael->email, '10,00', $idempotencyKey))
            ->assertSessionHasErrors('valor');

        $this->assertTrue($rafael->carteira->fresh()->saldo->isZero());
    }

    public function test_mesma_key_com_outro_valor_e_recusada(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $idempotencyKey = (string) Str::uuid();

        $this->actingAs($helena)->post('/transferencias', $this->dados($marina->email, '10,00', $idempotencyKey));
        $this->actingAs($helena)
            ->post('/transferencias', $this->dados($marina->email, '50,00', $idempotencyKey))
            ->assertSessionHasErrors('valor');

        $this->assertSame(9000, $helena->carteira->fresh()->saldo->cents());
        $this->assertSame(1000, $marina->carteira->fresh()->saldo->cents());
    }

    public function test_falha_no_meio_nao_grava_nada(): void
    {
        $helena = $this->usuarioComSaldo(10000);
        $marina = $this->usuarioComSaldo(0);
        $operacoesAntes = Operacao::count();

        Event::listen('eloquent.created: ' . Lancamento::class, function () {
            throw new RuntimeException('falha forçada depois do primeiro lançamento');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($helena)->post('/transferencias', $this->dados($marina->email, '10,00'));
            $this->fail('A transferência deveria ter falhado.');
        } catch (RuntimeException $e) {
            $this->assertSame(10000, $helena->carteira->fresh()->saldo->cents());
            $this->assertTrue($marina->carteira->fresh()->saldo->isZero());
            $this->assertSame($operacoesAntes, Operacao::count());
            $this->assertSaldosBatemComLancamentos();
        }
    }

    public function test_visitante_nao_transfere(): void
    {
        $marina = User::factory()->create();

        $this->post('/transferencias', $this->dados($marina->email, '1,00'))->assertRedirect('/login');
    }

    /** @return array<string, string> */
    private function dados(string $email, string $valor, ?string $idempotencyKey = null): array
    {
        return [
            'email' => $email,
            'valor' => $valor,
            'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
        ];
    }
}
