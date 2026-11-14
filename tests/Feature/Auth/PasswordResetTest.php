<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_recuperacao_de_senha_nao_revela_se_o_email_existe(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $comConta = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $semConta = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'ninguem@example.com']);

        $neutra = 'Se existir uma conta com esse e-mail, enviamos um link para criar uma senha nova.';
        $comConta->assertSessionHas('status', $neutra)->assertSessionHasNoErrors();
        $semConta->assertSessionHas('status', $neutra)->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Ipe#Roxo2026',
                'password_confirmation' => 'Ipe#Roxo2026',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });
    }

    public function test_redefinir_a_senha_encerra_as_sessoes_abertas_em_outros_lugares(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $outro = User::factory()->create();
        foreach ([$user, $outro] as $dono) {
            DB::table('sessions')->insert([
                'id' => Str::random(40), 'user_id' => $dono->id, 'ip_address' => '10.0.0.1',
                'user_agent' => 'outro navegador', 'payload' => '', 'last_activity' => time(),
            ]);
        }

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Ipe#Roxo2026',
                'password_confirmation' => 'Ipe#Roxo2026',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $outro->id)->count());
    }
}
