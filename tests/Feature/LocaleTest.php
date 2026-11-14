<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_mensagens_de_validacao_saem_em_portugues(): void
    {
        $erros = Validator::make(['cidade' => ''], ['cidade' => 'required'])->errors();

        $this->assertSame('pt_BR', app()->getLocale());
        $this->assertStringContainsString('obrigatório', $erros->first('cidade'));
    }

    public function test_aplicacao_e_banco_gravam_em_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', DB::selectOne('select @@session.time_zone as tz')->tz);
    }

    public function test_horario_e_exibido_no_fuso_de_brasilia(): void
    {
        $gravadoEmUtc = Carbon::parse('2026-11-13 13:00:00', 'UTC');

        $exibido = $gravadoEmUtc->copy()->setTimezone(config('app.display_timezone'));

        $this->assertSame('America/Sao_Paulo', config('app.display_timezone'));
        $this->assertSame('13/11/2026 10:00', $exibido->format('d/m/Y H:i'));
    }
}
