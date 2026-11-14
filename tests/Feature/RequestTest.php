<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\PreparaCarteiras;
use Tests\TestCase;

class RequestTest extends TestCase
{
    use RefreshDatabase;
    use PreparaCarteiras;

    public function test_toda_resposta_tem_um_request_id(): void
    {
        $id = $this->get('/login')->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $id));
    }

    public function test_request_id_recebido_e_reaproveitado(): void
    {
        $this->withHeader('X-Request-Id', 'abc-123')
            ->get('/login')
            ->assertHeader('X-Request-Id', 'abc-123');
    }

    public function test_request_id_malformado_e_substituido(): void
    {
        $id = $this->withHeader('X-Request-Id', "abc\r\nSet-Cookie: x=1")
            ->get('/login')
            ->headers->get('X-Request-Id');

        $this->assertTrue(Str::isUuid((string) $id));
    }

    public function test_logs_da_requisicao_levam_request_id_e_usuario(): void
    {
        $helena = $this->usuarioComSaldo(0);
        Log::spy();

        $this->actingAs($helena)->withHeader('X-Request-Id', 'req-1')->get('/dashboard');

        Log::shouldHaveReceived('withContext')->with(['request_id' => 'req-1', 'user_id' => $helena->id]);
    }

    public function test_respostas_tem_headers_de_seguranca(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
