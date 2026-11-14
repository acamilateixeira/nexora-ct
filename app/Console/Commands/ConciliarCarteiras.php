<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Carteira\Conciliar;
use Illuminate\Console\Command;

class ConciliarCarteiras extends Command
{
    protected $signature = 'carteira:conciliar';

    protected $description = 'Confere se os saldos batem com o livro-razão (não corrige nada)';

    public function handle(Conciliar $conciliar): int
    {
        $problemas = $conciliar->handle();

        if ($problemas === []) {
            $this->info('Nenhuma divergência.');

            return 0;
        }

        $this->table(['Problema', 'Detalhes'], array_map(function ($problema) {
            return [$problema[0], json_encode($problema[1])];
        }, $problemas));

        return 1;
    }
}
