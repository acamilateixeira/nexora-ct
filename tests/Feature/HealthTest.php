<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_responde_que_o_processo_esta_vivo(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_ready_confirma_que_o_banco_responde(): void
    {
        $this->getJson('/ready')
            ->assertOk()
            ->assertJsonPath('checks.database', 'ok');
    }
}
