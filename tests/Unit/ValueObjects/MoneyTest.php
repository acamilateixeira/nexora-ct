<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\Exceptions\Carteira\ValorInvalido;
use App\ValueObjects\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('valoresDigitados')]
    public function test_converte_o_valor_digitado_em_centavos(string $digitado, int $centavos): void
    {
        $this->assertSame($centavos, Money::fromDecimalString($digitado)->cents());
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function valoresDigitados(): array
    {
        return [
            'vírgula decimal' => ['10,50', 1050],
            'ponto decimal' => ['10.50', 1050],
            'uma casa decimal' => ['10,5', 1050],
            'sem casas decimais' => ['10', 1000],
            'milhar com ponto e vírgula decimal' => ['1.234,56', 123456],
            'centavos' => ['0,01', 1],
            'espaços em volta' => ['  25,00 ', 2500],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_recusa_valor_digitado_invalido(string $digitado): void
    {
        $this->expectException(ValorInvalido::class);

        Money::fromDecimalString($digitado);
    }

    /** @return array<string, array{0: string}> */
    public static function valoresInvalidos(): array
    {
        return [
            'vazio' => [''],
            'texto' => ['dez reais'],
            'três casas decimais' => ['10,505'],
            'negativo' => ['-10,00'],
            'milhar mal formado' => ['1.23,00'],
            'notação científica' => ['1e3'],
            'grande demais' => ['10000000000000,00'],
        ];
    }

    public function test_soma_e_subtrai_sem_erro_de_ponto_flutuante(): void
    {
        $dezCentavos = Money::ofCents(10);
        $vinteCentavos = Money::ofCents(20);

        $this->assertSame(30, $dezCentavos->add($vinteCentavos)->cents());
        $this->assertSame(-10, $dezCentavos->subtract($vinteCentavos)->cents());
    }

    public function test_e_imutavel(): void
    {
        $original = Money::ofCents(100);

        $original->add(Money::ofCents(50));

        $this->assertSame(100, $original->cents());
    }

    public function test_saldo_negativo_mais_deposito_soma_ao_valor(): void
    {
        $saldoNegativo = Money::ofCents(-5000);

        $this->assertSame(5000, $saldoNegativo->add(Money::ofCents(10000))->cents());
    }

    public function test_compara_valores(): void
    {
        $cinco = Money::ofCents(500);
        $dez = Money::ofCents(1000);

        $this->assertTrue($cinco->isLessThan($dez));
        $this->assertFalse($dez->isLessThan($cinco));
        $this->assertFalse($cinco->isLessThan(Money::ofCents(500)));
        $this->assertTrue($cinco->equals(Money::ofCents(500)));
        $this->assertTrue(Money::ofCents(-1)->isNegative());
        $this->assertTrue(Money::zero()->isZero());
        $this->assertTrue($cinco->isPositive());
    }

    public function test_formata_em_reais(): void
    {
        $this->assertSame('R$ 1.234,56', Money::ofCents(123456)->format());
        $this->assertSame('R$ 0,05', Money::ofCents(5)->format());
        $this->assertSame('-R$ 50,00', Money::ofCents(-5000)->format());
    }

    public function test_valor_de_operacao_precisa_ser_positivo(): void
    {
        $this->expectException(ValorInvalido::class);

        Money::ofCents(0)->assertPositive();
    }

    public function test_inverte_o_sinal(): void
    {
        $this->assertSame(-150, Money::ofCents(150)->negate()->cents());
        $this->assertSame(150, Money::ofCents(-150)->negate()->cents());
    }

    public function test_recusa_inverter_o_menor_inteiro(): void
    {
        $this->expectException(ValorInvalido::class);

        Money::ofCents(PHP_INT_MIN)->negate();
    }

    public function test_recusa_resultado_que_estoura_o_inteiro(): void
    {
        $this->expectException(ValorInvalido::class);

        Money::ofCents(PHP_INT_MAX)->add(Money::ofCents(1));
    }
}
