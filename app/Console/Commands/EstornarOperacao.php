<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Carteira\Estornar;
use App\Exceptions\Carteira\CarteiraException;
use App\Models\Operacao;
use Illuminate\Console\Command;

class EstornarOperacao extends Command
{
    protected $signature = 'carteira:estornar {uuid} {--motivo= : obrigatório} {--operador= : quem está estornando, obrigatório}';

    protected $description = 'Estorna uma operação depois de investigada (operador)';

    public function handle(Estornar $estornar): int
    {
        $motivo = trim($this->opcao('motivo'));
        $operador = trim($this->opcao('operador'));
        if ($motivo === '' || $operador === '') {
            $this->error('Informe o motivo e quem está estornando: --motivo="..." --operador="..."');

            return 1;
        }

        $operacao = Operacao::where('uuid', $this->argument('uuid'))->first();
        if (! $operacao) {
            $this->error('Operação não encontrada.');

            return 1;
        }

        try {
            $estorno = $estornar->handle($operacao, $motivo, null, mb_substr($operador, 0, 100));
        } catch (CarteiraException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info("Estorno {$estorno->uuid} criado.");

        return 0;
    }

    private function opcao(string $nome): string
    {
        $valor = $this->option($nome);

        return is_string($valor) ? $valor : '';
    }
}
