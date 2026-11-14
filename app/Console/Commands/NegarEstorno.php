<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Carteira\RecusarEstorno;
use App\Exceptions\Carteira\CarteiraException;
use App\Models\Operacao;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NegarEstorno extends Command
{
    protected $signature = 'carteira:negar-estorno {uuid} {--motivo= : obrigatório, aparece para o cliente} {--operador= : quem analisou, obrigatório}';

    protected $description = 'Nega a contestação de uma transferência depois da análise (operador)';

    public function handle(RecusarEstorno $recusar): int
    {
        $motivo = trim(is_string($this->option('motivo')) ? $this->option('motivo') : '');
        $operador = trim(is_string($this->option('operador')) ? $this->option('operador') : '');
        if ($motivo === '' || $operador === '') {
            $this->error('Informe o motivo e quem analisou: --motivo="..." --operador="..."');

            return 1;
        }

        $operacao = Operacao::where('uuid', $this->argument('uuid'))->first();
        if (! $operacao) {
            $this->error('Operação não encontrada.');

            return 1;
        }

        try {
            $recusar->handle($operacao, $motivo, mb_substr($operador, 0, 100));
        } catch (CarteiraException | ModelNotFoundException $e) {
            $this->error($e instanceof ModelNotFoundException ? 'Esta transferência não tem contestação.' : $e->getMessage());

            return 1;
        }

        $this->info('Contestação negada.');

        return 0;
    }
}
