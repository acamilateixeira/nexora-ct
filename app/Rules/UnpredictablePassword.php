<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

final class UnpredictablePassword implements Rule, DataAwareRule
{
    private const TAMANHO_DA_SEQUENCIA = 4;

    private const LINHAS_DO_TECLADO = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];

    private const PALAVRAS_COMUNS = [
        'abc', 'acesso', 'admin', 'administrador', 'amor', 'banco', 'bemvindo', 'brasil', 'carteira',
        'corinthians', 'cruzeiro', 'deus', 'dragon', 'familia', 'flamengo', 'gremio', 'iloveyou', 'internacional',
        'jesus', 'login', 'master', 'mestre', 'monkey', 'mudar', 'mudarsenha', 'palmeiras', 'pass', 'password',
        'princesa', 'qwerty', 'santos', 'saopaulo', 'secret', 'segredo', 'senha', 'senhaforte', 'teste', 'test',
        'user', 'usuario', 'vasco', 'welcome',
    ];

    /** @var array<string, mixed> */
    private $data = [];

    /** @var string */
    private $mensagem = '';

    /** @param array<string, mixed> $data */
    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @param string $attribute
     */
    public function passes($attribute, $value): bool
    {
        if (! is_string($value)) {
            return true;
        }

        $senha = mb_strtolower(Str::ascii($value));

        if (preg_match('/(.)\1\1/u', $senha) === 1) {
            $this->mensagem = 'A senha não pode repetir o mesmo caractere três vezes seguidas, como 111 ou aaa.';

            return false;
        }

        if ($this->temSequencia($senha)) {
            $this->mensagem = 'A senha não pode ter sequências como 1234, abcd ou qwer.';

            return false;
        }

        $letras = (string) preg_replace('/[^a-z]/', '', $senha);
        if (in_array($letras, self::PALAVRAS_COMUNS, true)) {
            $this->mensagem = 'Essa senha é fácil de adivinhar. Evite palavras comuns como senha, admin ou nome de time.';

            return false;
        }

        if ($this->contemDadosPessoais($senha)) {
            $this->mensagem = 'A senha não pode conter seu nome ou seu e-mail.';

            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->mensagem;
    }

    private function temSequencia(string $senha): bool
    {
        $tamanho = strlen($senha);

        for ($i = 0; $i <= $tamanho - self::TAMANHO_DA_SEQUENCIA; $i++) {
            $trecho = substr($senha, $i, self::TAMANHO_DA_SEQUENCIA);

            if (ctype_alnum($trecho) && ($this->mesmoPasso($trecho, 1) || $this->mesmoPasso($trecho, -1))) {
                return true;
            }

            foreach (self::LINHAS_DO_TECLADO as $linha) {
                if (strpos($linha, $trecho) !== false || strpos(strrev($linha), $trecho) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function mesmoPasso(string $trecho, int $passo): bool
    {
        for ($i = 1; $i < strlen($trecho); $i++) {
            if (ord($trecho[$i]) - ord($trecho[$i - 1]) !== $passo) {
                return false;
            }
        }

        return true;
    }

    private function contemDadosPessoais(string $senha): bool
    {
        $partes = [];

        $nome = $this->data['name'] ?? null;
        if (is_string($nome)) {
            $partes = preg_split('/\s+/', mb_strtolower(Str::ascii($nome))) ?: [];
        }

        $email = $this->data['email'] ?? null;
        if (is_string($email) && strpos($email, '@') !== false) {
            $partes[] = mb_strtolower(Str::ascii(strstr($email, '@', true) ?: ''));
        }

        foreach ($partes as $parte) {
            if (strlen($parte) >= 3 && strpos($senha, $parte) !== false) {
                return true;
            }
        }

        return false;
    }
}
