<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;
    use PreparaCarteiras;

    public function test_valor_enviado_como_array_retorna_erro_de_validacao(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->from('/depositos/novo')
            ->post('/depositos', ['valor' => ['10'], 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect('/depositos/novo')
            ->assertSessionHasErrors('valor');

        $this->actingAs($helena)->get('/depositos/novo')->assertOk();
    }

    public function test_email_enviado_como_array_retorna_erro_de_validacao(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->from('/transferencias/nova')
            ->post('/transferencias/revisar', ['email' => ['marina@example.com'], 'valor' => '1,00', 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect('/transferencias/nova');

        $this->actingAs($helena)->get('/transferencias/nova')->assertOk();
        $this->actingAs($helena)->get('/transferencias/nova?email[]=x&valor[]=1')->assertOk();
    }

    public function test_parametros_da_url_sao_escapados_na_tela(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->get('/transferencias/nova?email=' . urlencode('"><script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
