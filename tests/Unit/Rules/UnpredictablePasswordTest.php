<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\UnpredictablePassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UnpredictablePasswordTest extends TestCase
{
    #[DataProvider('senhasPrevisiveis')]
    public function test_senha_previsivel_e_recusada(string $senha, string $mensagem): void
    {
        $regra = (new UnpredictablePassword())->setData(['name' => 'Helena Prado', 'email' => 'helena.souza@exemplo.com']);

        $this->assertFalse($regra->passes('password', $senha));
        $this->assertStringContainsString($mensagem, $regra->message());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function senhasPrevisiveis(): array
    {
        return [
            'números em sequência' => ['Xy#12345', 'sequências'],
            'números em sequência decrescente' => ['Xy#98765', 'sequências'],
            'letras em sequência' => ['Abcd#9182', 'sequências'],
            'linha do teclado' => ['Qwer#9182', 'sequências'],
            'linha do teclado ao contrário' => ['Rewq#9182', 'sequências'],
            'caractere repetido' => ['Kp#7111xz', 'repetir'],
            'letra repetida sem diferenciar maiúscula' => ['AaA#91kz', 'repetir'],
            'palavra comum com número e símbolo' => ['Senha@2026', 'fácil de adivinhar'],
            'time de futebol' => ['Flamengo@81', 'fácil de adivinhar'],
            'palavra comum com acento' => ['Usuário#81', 'fácil de adivinhar'],
            'nome da pessoa' => ['Prado#9182k', 'nome ou seu e-mail'],
            'parte do e-mail' => ['X#helena.souza9', 'nome ou seu e-mail'],
        ];
    }

    #[DataProvider('senhasAceitas')]
    public function test_senha_sem_padrao_previsivel_e_aceita(string $senha): void
    {
        $regra = (new UnpredictablePassword())->setData(['name' => 'Helena Prado', 'email' => 'helena.souza@exemplo.com']);

        $this->assertTrue($regra->passes('password', $senha));
    }

    /** @return array<string, array{0: string}> */
    public static function senhasAceitas(): array
    {
        return [
            'palavras sem sequência' => ['Ipe#Roxo2026'],
            'aleatória' => ['Zx#81Kp0q'],
            'só dois iguais seguidos' => ['Mm#8811kz'],
            'três números fora de ordem' => ['Kz#9183px'],
        ];
    }
}
