<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\DB;

trait ConfereLivroRazao
{
    protected function assertSaldosBatemComLancamentos(): void
    {
        $divergentes = DB::table('carteiras')
            ->leftJoin('lancamentos', 'lancamentos.carteira_id', '=', 'carteiras.id')
            ->groupBy('carteiras.id', 'carteiras.saldo')
            ->havingRaw('carteiras.saldo <> COALESCE(SUM(lancamentos.valor), 0)')
            ->pluck('carteiras.id');

        $this->assertCount(0, $divergentes, 'Carteiras com saldo diferente da soma dos lançamentos: ' . $divergentes->implode(', '));
    }
}
