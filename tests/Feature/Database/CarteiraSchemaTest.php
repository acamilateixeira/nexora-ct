<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Carteira;
use App\Models\Lancamento;
use App\Models\Operacao;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CarteiraSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_e_gravado_em_centavos_e_lido_como_money(): void
    {
        $carteira = $this->carteira();
        $this->deposito($carteira, ['valor' => Money::ofCents(123456)])->creditar($carteira);

        $this->assertSame(123456, (int) DB::table('carteiras')->where('id', $carteira->id)->value('saldo'));
        $this->assertTrue($carteira->fresh()->saldo->equals(Money::ofCents(123456)));
    }

    public function test_cada_usuario_tem_uma_carteira_so(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('carteiras_user_id_unique', fn () => Carteira::create(['user_id' => $carteira->user_id]));
    }

    public function test_operacao_ganha_uuid_ao_ser_criada(): void
    {
        $operacao = $this->deposito($this->carteira());

        $this->assertTrue(Str::isUuid($operacao->uuid));
        $this->assertSame('uuid', $operacao->getRouteKeyName());
    }

    public function test_valor_zero_e_recusado(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('operacoes_valor_check', fn () => $this->deposito($carteira, ['valor' => Money::zero()]));
    }

    public function test_tipo_desconhecido_e_recusado(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('operacoes_tipo_check', fn () => $this->deposito($carteira, ['tipo' => 'saque']));
    }

    public function test_status_desconhecido_e_recusado(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('operacoes_status_check', fn () => $this->deposito($carteira, ['status' => 'pendente']));
    }

    public function test_transferencia_para_a_propria_carteira_e_recusada(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('operacoes_mesma_carteira_check', fn () => $this->deposito($carteira, [
            'tipo' => Operacao::TIPO_TRANSFERENCIA,
            'carteira_origem_id' => $carteira->id,
        ]));
    }

    public function test_transferencia_sem_origem_e_recusada(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('operacoes_transferencia_check', fn () => $this->deposito($carteira, ['tipo' => Operacao::TIPO_TRANSFERENCIA]));
    }

    public function test_estorno_precisa_apontar_para_a_operacao_original(): void
    {
        $carteira = $this->carteira();

        $this->assertViolaRestricao('operacoes_estorno_check', fn () => $this->deposito($carteira, ['tipo' => Operacao::TIPO_ESTORNO]));
    }

    public function test_so_estorno_aponta_para_outra_operacao(): void
    {
        $carteira = $this->carteira();
        $original = $this->deposito($carteira);

        $this->assertViolaRestricao('operacoes_estorno_check', fn () => $this->deposito($carteira, ['estorna_id' => $original->id]));
    }

    public function test_operacao_so_pode_ser_estornada_uma_vez(): void
    {
        $carteira = $this->carteira();
        $original = $this->deposito($carteira);
        $this->estorno($original, $carteira);

        $this->assertViolaRestricao('operacoes_estorna_id_unique', fn () => $this->estorno($original, $carteira));
    }

    public function test_toda_operacao_tem_um_autor_identificado(): void
    {
        $carteira = $this->carteira();
        $original = $this->deposito($carteira);

        $this->assertViolaRestricao('operacoes_autor_check', fn () => Operacao::create([
            'tipo' => Operacao::TIPO_ESTORNO,
            'status' => Operacao::STATUS_CONCLUIDA,
            'valor' => $original->valor,
            'carteira_origem_id' => $carteira->id,
            'estorna_id' => $original->id,
            'motivo' => 'sem autor',
        ]));
    }

    public function test_mesma_idempotency_key_da_mesma_pessoa_e_recusada(): void
    {
        $carteira = $this->carteira();
        $idempotencyKey = (string) Str::uuid();
        $this->deposito($carteira, ['idempotency_key' => $idempotencyKey]);

        $this->assertViolaRestricao('operacoes_criada_por_idempotency_key_unique', fn () => $this->deposito($carteira, ['idempotency_key' => $idempotencyKey]));
    }

    public function test_mesma_idempotency_key_de_pessoas_diferentes_e_aceita(): void
    {
        $idempotencyKey = (string) Str::uuid();
        $this->deposito($this->carteira(), ['idempotency_key' => $idempotencyKey]);
        $this->deposito($this->carteira(), ['idempotency_key' => $idempotencyKey]);

        $this->assertSame(2, Operacao::where('idempotency_key', $idempotencyKey)->count());
    }

    public function test_lancamento_com_valor_zero_e_recusado(): void
    {
        $carteira = $this->carteira();
        $operacao = $this->deposito($carteira);

        $this->assertViolaRestricao('valor diferente da operação', fn () => Lancamento::create([
            'operacao_id' => $operacao->id,
            'carteira_id' => $carteira->id,
            'valor' => Money::zero(),
            'saldo_apos' => Money::zero(),
        ]));
    }

    public function test_lancamento_guarda_valor_e_saldo_apos(): void
    {
        $carteira = $this->carteira();
        $operacao = $this->deposito($carteira);

        $lancamento = Lancamento::create([
            'operacao_id' => $operacao->id,
            'carteira_id' => $carteira->id,
            'valor' => Money::ofCents(1000),
            'saldo_apos' => Money::ofCents(1000),
        ])->fresh();

        $this->assertSame(1000, $lancamento->valor->cents());
        $this->assertSame(1000, $lancamento->saldo_apos->cents());
        $this->assertSame(1000, $carteira->fresh()->saldo->cents());
        $this->assertNotNull($lancamento->created_at);
        $this->assertTrue($lancamento->carteira->is($carteira));
        $this->assertTrue($lancamento->operacao->is($operacao));
    }

    private function assertViolaRestricao(string $restricao, callable $operacao): void
    {
        try {
            $operacao();
        } catch (QueryException $e) {
            $this->assertStringContainsString($restricao, $e->getMessage());

            return;
        }

        $this->fail("O banco deveria ter recusado pela restrição {$restricao}.");
    }

    #[DataProvider('adulteracoesDiretas')]
    public function test_banco_recusa_adulteracao_direta(string $mensagem, callable $adulteracao): void
    {
        $helena = $this->carteira();
        $marina = $this->carteira();
        $deposito = $this->deposito($helena, ['valor' => Money::ofCents(1000)]);
        $deposito->creditar($helena);

        $this->assertViolaRestricao($mensagem, function () use ($adulteracao, $helena, $marina, $deposito) {
            $adulteracao($helena, $marina, $deposito);
        });

        $this->assertSame(1000, (int) DB::table('carteiras')->where('id', $helena->id)->value('saldo'));
    }

    /** @return array<string, array{0: string, 1: callable}> */
    public static function adulteracoesDiretas(): array
    {
        return [
            'mudar o saldo' => ['saldo só muda por lançamento', function (Carteira $helena) {
                DB::table('carteiras')->where('id', $helena->id)->update(['saldo' => 999999]);
            }],
            'carteira nascendo com saldo' => ['carteira nasce com saldo zero', function () {
                DB::table('carteiras')->insert(['user_id' => User::factory()->create()->id, 'saldo' => 500]);
            }],
            'trocar o dono da carteira' => ['carteira não troca de dono', function (Carteira $helena, Carteira $marina) {
                DB::table('carteiras')->where('id', $helena->id)->update(['user_id' => $marina->user_id]);
            }],
            'apagar carteira' => ['carteira não é apagada', function (Carteira $helena) {
                DB::table('carteiras')->where('id', $helena->id)->delete();
            }],
            'alterar lançamento' => ['lançamento não é alterado', function (Carteira $helena) {
                DB::table('lancamentos')->where('carteira_id', $helena->id)->update(['valor' => 5000]);
            }],
            'apagar lançamento' => ['lançamento não é apagado', function (Carteira $helena) {
                DB::table('lancamentos')->where('carteira_id', $helena->id)->delete();
            }],
            'lançamento em carteira de fora da operação' => ['carteira não participa da operação', function (Carteira $helena, Carteira $marina, Operacao $deposito) {
                DB::table('lancamentos')->insert(['operacao_id' => $deposito->id, 'carteira_id' => $marina->id, 'valor' => 1000, 'saldo_apos' => 1000]);
            }],
            'lançamento com saldo inventado' => ['saldo_apos não continua o saldo', function (Carteira $helena) {
                $outro = Operacao::create(['tipo' => Operacao::TIPO_DEPOSITO, 'status' => Operacao::STATUS_CONCLUIDA, 'valor' => Money::ofCents(10), 'carteira_destino_id' => $helena->id, 'criada_por' => $helena->user_id]);
                DB::table('lancamentos')->insert(['operacao_id' => $outro->id, 'carteira_id' => $helena->id, 'valor' => 10, 'saldo_apos' => 999999]);
            }],
            'lançar a mesma operação duas vezes' => ['lancamentos_operacao_id_carteira_id_unique', function (Carteira $helena, Carteira $marina, Operacao $deposito) {
                DB::table('lancamentos')->insert(['operacao_id' => $deposito->id, 'carteira_id' => $helena->id, 'valor' => 1000, 'saldo_apos' => 2000]);
            }],
            'transferência sem saldo' => ['saldo insuficiente', function (Carteira $helena, Carteira $marina) {
                $transferencia = Operacao::create(['tipo' => Operacao::TIPO_TRANSFERENCIA, 'status' => Operacao::STATUS_CONCLUIDA, 'valor' => Money::ofCents(5000), 'carteira_origem_id' => $helena->id, 'carteira_destino_id' => $marina->id, 'criada_por' => $helena->user_id]);
                DB::table('lancamentos')->insert(['operacao_id' => $transferencia->id, 'carteira_id' => $helena->id, 'valor' => -5000, 'saldo_apos' => -4000]);
            }],
            'mudar o valor de uma operação' => ['operação não é alterada', function (Carteira $helena, Carteira $marina, Operacao $deposito) {
                DB::table('operacoes')->where('id', $deposito->id)->update(['valor' => 1]);
            }],
            'marcar como estornada sem estorno' => ['só fica estornada com o estorno gravado', function (Carteira $helena, Carteira $marina, Operacao $deposito) {
                DB::table('operacoes')->where('id', $deposito->id)->update(['status' => Operacao::STATUS_ESTORNADA]);
            }],
            'apagar operação' => ['operação não é apagada', function (Carteira $helena, Carteira $marina, Operacao $deposito) {
                DB::table('operacoes')->where('id', $deposito->id)->delete();
            }],
        ];
    }

    public function test_banco_recusa_transferencia_que_gasta_o_valor_bloqueado(): void
    {
        $helena = $this->carteira();
        $marina = $this->carteira();
        $this->deposito($helena, ['valor' => Money::ofCents(1000)])->creditar($helena);
        DB::table('carteiras')->where('id', $helena->id)->update(['bloqueado' => 600]);

        $this->assertViolaRestricao('saldo insuficiente', function () use ($helena, $marina) {
            $this->transferencia($helena, $marina, 500)->debitarMesmoSemSaldo($helena->fresh());
        });

        $this->transferencia($helena, $marina, 400)->debitar($helena->fresh());

        $this->assertSame(600, (int) DB::table('carteiras')->where('id', $helena->id)->value('saldo'));
    }

    public function test_estorno_pode_deixar_saldo_negativo_mesmo_pedido_pelo_usuario(): void
    {
        $helena = $this->carteira();
        $marina = $this->carteira();
        $deposito = $this->deposito($helena, ['valor' => Money::ofCents(1000)]);
        $deposito->creditar($helena);
        $transferencia = Operacao::create(['tipo' => Operacao::TIPO_TRANSFERENCIA, 'status' => Operacao::STATUS_CONCLUIDA, 'valor' => Money::ofCents(1000), 'carteira_origem_id' => $helena->id, 'carteira_destino_id' => $marina->id, 'criada_por' => $helena->user_id]);
        $transferencia->debitar($helena->fresh());
        $transferencia->creditar($marina->fresh());

        $estorno = Operacao::create(['tipo' => Operacao::TIPO_ESTORNO, 'status' => Operacao::STATUS_CONCLUIDA, 'valor' => Money::ofCents(1000), 'carteira_origem_id' => $helena->id, 'estorna_id' => $deposito->id, 'criada_por' => $helena->user_id]);
        $estorno->debitarMesmoSemSaldo($helena->fresh());

        $this->assertSame(-1000, (int) DB::table('carteiras')->where('id', $helena->id)->value('saldo'));
    }

    private function carteira(): Carteira
    {
        return Carteira::create(['user_id' => User::factory()->create()->id]);
    }

    /** @param array<string, mixed> $campos */
    private function deposito(Carteira $carteira, array $campos = []): Operacao
    {
        return Operacao::create(array_merge([
            'tipo' => Operacao::TIPO_DEPOSITO,
            'status' => Operacao::STATUS_CONCLUIDA,
            'valor' => Money::ofCents(1000),
            'carteira_destino_id' => $carteira->id,
            'criada_por' => $carteira->user_id,
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => str_repeat('a', 64),
        ], $campos));
    }

    private function transferencia(Carteira $origem, Carteira $destino, int $centavos): Operacao
    {
        return Operacao::create([
            'tipo' => Operacao::TIPO_TRANSFERENCIA,
            'status' => Operacao::STATUS_CONCLUIDA,
            'valor' => Money::ofCents($centavos),
            'carteira_origem_id' => $origem->id,
            'carteira_destino_id' => $destino->id,
            'criada_por' => $origem->user_id,
        ]);
    }

    private function estorno(Operacao $original, Carteira $carteira): Operacao
    {
        return Operacao::create([
            'tipo' => Operacao::TIPO_ESTORNO,
            'status' => Operacao::STATUS_CONCLUIDA,
            'valor' => $original->valor,
            'carteira_origem_id' => $carteira->id,
            'estorna_id' => $original->id,
            'criada_por' => $carteira->user_id,
            'motivo' => 'Depósito por engano.',
        ]);
    }
}
