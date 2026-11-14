<?php

declare(strict_types=1);

use App\Actions\Carteira\Estornar;
use App\Actions\Carteira\SolicitarEstorno;
use App\Actions\Carteira\Transferir;
use App\Exceptions\Carteira\CarteiraException;
use App\Models\Operacao;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$comecarEm = (float) $argv[1];
$acao = $argv[2];
$args = array_slice($argv, 3);

while (microtime(true) < $comecarEm) {
    usleep(1000);
}

try {
    if ($acao === 'transferir') {
        [$de, $para, $valor, $idempotencyKey] = $args;
        $operacao = app(Transferir::class)->handle(User::findOrFail($de), $para, Money::fromDecimalString($valor), $idempotencyKey);
    } elseif ($acao === 'contestar') {
        [$uuid, $quem] = $args;
        $operacao = Operacao::where('uuid', $uuid)->firstOrFail();
        app(SolicitarEstorno::class)->handle($operacao, User::findOrFail($quem), 'teste de concorrência');
    } else {
        [$uuid, $quem] = $args;
        $operacao = app(Estornar::class)->handle(Operacao::where('uuid', $uuid)->firstOrFail(), 'teste de concorrência', User::findOrFail($quem));
    }

    echo 'ok ' . $operacao->uuid;
} catch (CarteiraException $e) {
    echo 'recusado ' . $e->errorCode();
}
