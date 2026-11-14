<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_login_nao_cria_cookie_de_lembrar_mesmo_se_pedido(): void
    {
        $user = User::factory()->create();
        $tokenAntes = $user->remember_token;

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'on']);

        $this->assertAuthenticated();
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertStringStartsNotWith('remember_web_', $cookie->getName());
        }
        $this->assertSame($tokenAntes, $user->fresh()->remember_token);
    }

    public function test_sessao_expira_depois_de_15_minutos_sem_uso(): void
    {
        $this->assertSame(15, (int) config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
    }
}
