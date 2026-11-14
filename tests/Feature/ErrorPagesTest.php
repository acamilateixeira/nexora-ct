<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;
    use PreparaCarteiras;

    public function test_rota_inexistente_exibe_pagina_404(): void
    {
        $this->get('/nao-existe')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertDontSee('Not Found');
    }

    public function test_metodo_nao_permitido_exibe_pagina_405(): void
    {
        $helena = $this->usuarioComSaldo(1000);

        $this->actingAs($helena)
            ->get('/transferencias/revisar')
            ->assertStatus(405)
            ->assertSee('Acesso inválido a esta página');
    }

    public function test_operacao_de_outro_usuario_exibe_pagina_403(): void
    {
        $helena = $this->usuarioComSaldo(1000);
        $rafael = $this->usuarioComSaldo(0);
        $deposito = $helena->carteira->lancamentos()->firstOrFail()->operacao;

        $this->actingAs($rafael)
            ->get("/operacoes/{$deposito->uuid}")
            ->assertForbidden()
            ->assertSee('Você não tem acesso a esta página');
    }

    public function test_limite_de_requisicoes_exibe_pagina_429(): void
    {
        $helena = $this->usuarioComSaldo(0);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($helena)->post('/depositos', ['valor' => '1,00', 'idempotency_key' => (string) Str::uuid()]);
        }

        $this->actingAs($helena)
            ->post('/depositos', ['valor' => '1,00', 'idempotency_key' => (string) Str::uuid()])
            ->assertStatus(429)
            ->assertSee('Muitas operações em pouco tempo');
    }
}
