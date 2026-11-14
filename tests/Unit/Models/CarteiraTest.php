<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\Carteira\SaldoInsuficiente;
use App\Exceptions\Carteira\ValorInvalido;
use App\Models\Carteira;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CarteiraTest extends TestCase
{
    public function test_carteira_nova_comeca_com_saldo_zero(): void
    {
        $this->assertTrue((new Carteira())->saldo->isZero());
    }

    public function test_deposito_em_saldo_negativo_soma_ao_valor(): void
    {
        $carteira = $this->carteiraCom(-5000);

        $carteira->creditar(Money::ofCents(10000));

        $this->assertSame(5000, $carteira->saldo->cents());
    }

    public function test_debito_com_saldo_suficiente_subtrai(): void
    {
        $carteira = $this->carteiraCom(10000);

        $carteira->debitar(Money::ofCents(2550));

        $this->assertSame(7450, $carteira->saldo->cents());
    }

    public function test_debito_de_todo_o_saldo_deixa_zero(): void
    {
        $carteira = $this->carteiraCom(10000);

        $carteira->debitar(Money::ofCents(10000));

        $this->assertTrue($carteira->saldo->isZero());
    }

    public function test_debito_sem_saldo_e_recusado_e_o_saldo_nao_muda(): void
    {
        $carteira = $this->carteiraCom(1000);

        try {
            $carteira->debitar(Money::ofCents(1001));
            $this->fail('Deveria recusar o débito sem saldo.');
        } catch (SaldoInsuficiente $e) {
            $this->assertSame(1000, $carteira->saldo->cents());
            $this->assertSame('saldo_insuficiente', $e->errorCode());
        }
    }

    public function test_debito_com_saldo_negativo_e_recusado(): void
    {
        $this->expectException(SaldoInsuficiente::class);

        $this->carteiraCom(-1)->debitar(Money::ofCents(1));
    }

    public function test_estorno_pode_deixar_saldo_negativo(): void
    {
        $carteira = $this->carteiraCom(3000);

        $carteira->debitarMesmoSemSaldo(Money::ofCents(5000));

        $this->assertSame(-2000, $carteira->saldo->cents());
    }

    #[DataProvider('operacoes')]
    public function test_recusa_valor_zero_ou_negativo(string $metodo, int $centavos): void
    {
        $this->expectException(ValorInvalido::class);

        $this->carteiraCom(10000)->{$metodo}(Money::ofCents($centavos));
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function operacoes(): array
    {
        return [
            'crédito zero' => ['creditar', 0],
            'crédito negativo' => ['creditar', -100],
            'débito zero' => ['debitar', 0],
            'débito negativo' => ['debitar', -100],
            'estorno zero' => ['debitarMesmoSemSaldo', 0],
            'estorno negativo' => ['debitarMesmoSemSaldo', -100],
        ];
    }

    private function carteiraCom(int $centavos): Carteira
    {
        $carteira = new Carteira();
        $carteira->saldo = Money::ofCents($centavos);

        return $carteira;
    }
}
