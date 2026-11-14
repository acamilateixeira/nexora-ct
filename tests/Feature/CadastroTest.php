<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Carteira\CriarCarteira;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_cria_a_carteira_com_saldo_zero(): void
    {
        $this->post('/register', $this->dados())->assertRedirect('/dashboard');

        $user = User::where('email', 'helena@example.com')->firstOrFail();
        $this->assertNotNull($user->carteira);
        $this->assertTrue($user->carteira->saldo->isZero());
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_ja_cadastrado_retorna_erro_de_unicidade(): void
    {
        User::factory()->create(['email' => 'helena@example.com']);

        $this->post('/register', $this->dados())
            ->assertSessionHasErrors(['email' => 'Já existe uma conta com esse e-mail. Entre ou recupere a senha.']);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    #[DataProvider('nomesComCaractereDeControle')]
    public function test_nome_com_caractere_de_controle_e_recusado(string $nome): void
    {
        $this->post('/register', $this->dados(['name' => $nome]))
            ->assertSessionHasErrors(['name' => 'O nome tem caracteres que não são aceitos.']);

        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{0: string}> */
    public static function nomesComCaractereDeControle(): array
    {
        return [
            'inversão de direção' => ["Marina\u{202E}Lopes"],
            'espaço de largura zero' => ["Bru\u{200B}no"],
            'caractere de controle' => ["Marina\x07"],
        ];
    }

    public function test_confirmacao_e_tamanho_minimo_da_senha(): void
    {
        $this->post('/register', $this->dados(['password' => '123', 'password_confirmation' => '456']))
            ->assertSessionHasErrors(['password' => 'As senhas não são iguais.']);

        $this->post('/register', $this->dados(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors(['password' => 'A senha precisa ter pelo menos 8 caracteres.']);
    }

    #[DataProvider('senhasFracas')]
    public function test_senha_fraca_e_recusada_com_o_motivo(string $senha, string $mensagem): void
    {
        $this->post('/register', $this->dados(['password' => $senha, 'password_confirmation' => $senha]))
            ->assertSessionHasErrors(['password' => $mensagem]);

        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function senhasFracas(): array
    {
        return [
            'só números em sequência' => ['12345678', 'A senha não pode ter sequências como 1234, abcd ou qwer.'],
            'sem maiúscula' => ['ipe#roxo2026', 'A senha precisa ter letras maiúsculas e minúsculas.'],
            'sem número' => ['Ipe#Roxo', 'A senha precisa ter pelo menos um número.'],
            'sem símbolo' => ['IpeRoxo2026', 'A senha precisa ter pelo menos um símbolo, como ! @ # ou $.'],
            'palavra óbvia' => ['Senha@2026', 'Essa senha é fácil de adivinhar. Evite palavras comuns como senha, admin ou nome de time.'],
        ];
    }

    public function test_erro_de_validacao_e_exibido_junto_ao_campo(): void
    {
        User::factory()->create(['email' => 'helena@example.com']);

        $this->from('/register')->post('/register', $this->dados());

        $this->get('/register')
            ->assertSee('id="email-erro"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Já existe uma conta com esse e-mail.');
    }

    #[DataProvider('emailsComQuebraDeLinha')]
    public function test_email_com_quebra_de_linha_e_recusado(string $email): void
    {
        $this->post('/register', $this->dados(['email' => $email]))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    /** @return array<string, array{0: string}> */
    public static function emailsComQuebraDeLinha(): array
    {
        return [
            'entre aspas' => ["\"helena\r\n x\"@example.com"],
            'em comentário' => ["helena(\r\n x)@example.com"],
            'antes do @' => ["helena\r\n @example.com"],
        ];
    }

    public function test_se_a_carteira_falhar_o_usuario_nao_e_criado(): void
    {
        $this->mock(CriarCarteira::class, function ($mock) {
            $mock->shouldReceive('handle')->andThrow(new RuntimeException('falha ao criar a carteira'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->post('/register', $this->dados());
            $this->fail('O cadastro deveria ter falhado.');
        } catch (RuntimeException $e) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('carteiras', 0);
            $this->assertGuest();
        }
    }

    public function test_login_bloqueia_depois_de_cinco_tentativas_erradas(): void
    {
        $user = User::factory()->create(['email' => 'helena@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'senha-errada']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringStartsWith('Muitas tentativas de login', session('errors')->first('email'));

        $this->assertGuest();
    }

    /**
     * @param array<string, string> $campos
     * @return array<string, string>
     */
    private function dados(array $campos = []): array
    {
        return array_merge([
            'name' => 'Helena',
            'email' => 'helena@example.com',
            'password' => 'Ipe#Roxo2026',
            'password_confirmation' => 'Ipe#Roxo2026',
        ], $campos);
    }
}
