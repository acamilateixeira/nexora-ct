<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Carteira\Estornar;
use App\Models\Carteira;
use App\Models\Operacao;
use App\Models\User;
use App\ValueObjects\Money;

trait PreparaCarteiras
{
    protected function usuarioComSaldo(int $centavos): User
    {
        $user = User::factory()->create();
        $carteira = Carteira::create(['user_id' => $user->id]);

        if ($centavos > 0) {
            $this->lancar(Operacao::TIPO_DEPOSITO, $centavos, null, $carteira, $user);
        }

        if ($centavos < 0) {
            $outra = Carteira::create(['user_id' => User::factory()->create()->id]);
            $deposito = $this->lancar(Operacao::TIPO_DEPOSITO, -$centavos, null, $carteira, $user);
            $this->lancar(Operacao::TIPO_TRANSFERENCIA, -$centavos, $carteira->fresh(), $outra, $user);
            app(Estornar::class)->handle($deposito, 'Depósito em duplicidade', null, 'Teste');
        }

        return $user->fresh();
    }

    private function lancar(string $tipo, int $centavos, ?Carteira $origem, Carteira $destino, User $user): Operacao
    {
        $valor = Money::ofCents($centavos);
        $operacao = Operacao::create([
            'tipo' => $tipo,
            'status' => Operacao::STATUS_CONCLUIDA,
            'valor' => $valor,
            'carteira_origem_id' => $origem ? $origem->id : null,
            'carteira_destino_id' => $destino->id,
            'criada_por' => $user->id,
        ]);

        if ($origem) {
            $operacao->debitar($origem);
        }
        $operacao->creditar($destino);

        return $operacao;
    }
}
