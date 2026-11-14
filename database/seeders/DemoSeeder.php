<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Carteira\CriarCarteira;
use App\Actions\Carteira\Depositar;
use App\Actions\Carteira\DevolverTransferencia;
use App\Actions\Carteira\Estornar;
use App\Actions\Carteira\RecusarEstorno;
use App\Actions\Carteira\SolicitarEstorno;
use App\Actions\Carteira\Transferir;
use App\Models\User;
use App\ValueObjects\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    private const ATENDIMENTO = 'Equipe de atendimento';

    /** @var CarbonImmutable */
    private $hoje;

    public function run(
        CriarCarteira $criarCarteira,
        Depositar $depositar,
        Transferir $transferir,
        DevolverTransferencia $devolver,
        Estornar $estornar,
        SolicitarEstorno $contestar,
        RecusarEstorno $negar
    ): void {
        if (User::where('email', 'helena@exemplo.com')->exists()) {
            return;
        }

        $this->hoje = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();

        $helena = $this->usuario('Helena Prado', 'helena@exemplo.com', $criarCarteira);
        $marina = $this->usuario('Marina Lopes', 'marina@exemplo.com', $criarCarteira);
        $rafael = $this->usuario('Rafael Nunes', 'rafael@exemplo.com', $criarCarteira);
        $pares = [[$helena, $marina], [$marina, $rafael], [$rafael, $helena]];

        try {
            $this->em(14, '09:12', function () use ($depositar, $helena, $marina, $rafael) {
                $depositar->handle($helena, $this->reais('2.000,00'), $this->key());
                $depositar->handle($marina, $this->reais('1.500,00'), $this->key());
                $depositar->handle($rafael, $this->reais('1.000,00'), $this->key());
            });

            $transferencias = [];
            foreach ($pares as $i => [$de, $para]) {
                $transferencias[$i] = $this->em(12, '10:0' . $i, function () use ($transferir, $de, $para) {
                    return $transferir->handle($de, $para->email, $this->reais('100,00'), $this->key());
                });
            }
            foreach ($pares as $i => [, $para]) {
                $this->em(12, '15:3' . $i, function () use ($devolver, $transferencias, $i, $para) {
                    $devolver->handle($transferencias[$i], $para, 'Recebi por engano');
                });
            }

            $transferencias = [];
            foreach ($pares as $i => [$de, $para]) {
                $transferencias[$i] = $this->em(10, '11:1' . $i, function () use ($transferir, $de, $para) {
                    return $transferir->handle($de, $para->email, $this->reais('80,00'), $this->key());
                });
            }
            foreach ($pares as $i => [$de]) {
                $this->em(9, '08:4' . $i, function () use ($contestar, $transferencias, $i, $de) {
                    $contestar->handle($transferencias[$i], $de, 'Não reconheço esta transferência');
                });
            }
            foreach ($transferencias as $i => $transferencia) {
                $this->em(8, '16:2' . $i, function () use ($estornar, $transferencia) {
                    $estornar->handle($transferencia, 'Contestação aprovada', null, self::ATENDIMENTO);
                });
            }

            $transferencias = [];
            foreach ($pares as $i => [$de, $para]) {
                $transferencias[$i] = $this->em(7, '14:0' . $i, function () use ($transferir, $de, $para) {
                    return $transferir->handle($de, $para->email, $this->reais('60,00'), $this->key());
                });
            }
            foreach ($pares as $i => [$de]) {
                $this->em(6, '09:5' . $i, function () use ($contestar, $transferencias, $i, $de) {
                    $contestar->handle($transferencias[$i], $de, 'Enviei o valor errado');
                });
            }
            foreach ($transferencias as $i => $transferencia) {
                $this->em(5, '17:1' . $i, function () use ($negar, $transferencia) {
                    $negar->handle($transferencia, 'Pagamento confirmado pelo recebedor', self::ATENDIMENTO);
                });
            }

            $transferencias = [];
            foreach ($pares as $i => [$de, $para]) {
                $transferencias[$i] = $this->em(4, '13:2' . $i, function () use ($transferir, $de, $para) {
                    return $transferir->handle($de, $para->email, $this->reais('40,00'), $this->key());
                });
            }
            foreach ($pares as $i => [$de]) {
                $this->em(3, '10:0' . $i, function () use ($contestar, $transferencias, $i, $de) {
                    $contestar->handle($transferencias[$i], $de, 'Golpe: não fiz esta transferência');
                });
            }

            $duplicados = [];
            foreach ([$helena, $marina, $rafael] as $i => $user) {
                $duplicados[$i] = $this->em(2, '09:0' . $i, function () use ($depositar, $user) {
                    return $depositar->handle($user, $this->reais('300,00'), $this->key());
                });
            }
            $this->em(2, '18:45', function () use ($transferir, $rafael, $helena) {
                $transferir->handle($rafael, $helena->email, $this->reais('1.200,00'), $this->key());
            });
            foreach ($duplicados as $i => $duplicado) {
                $this->em(1, '11:0' . $i, function () use ($estornar, $duplicado) {
                    $estornar->handle($duplicado, 'Depósito em duplicidade', null, self::ATENDIMENTO);
                });
            }

            $this->em(0, '08:30', function () use ($depositar, $rafael) {
                $depositar->handle($rafael, $this->reais('50,00'), $this->key());
            });
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @template T
     * @param callable(): T $acao
     * @return T
     */
    private function em(int $diasAtras, string $hora, callable $acao)
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        $quando = $this->hoje->subDays($diasAtras)->setTime($h, $m)->utc();
        Carbon::setTestNow($quando);
        CarbonImmutable::setTestNow($quando);

        return $acao();
    }

    private function reais(string $valor): Money
    {
        return Money::fromDecimalString($valor);
    }

    private function usuario(string $nome, string $email, CriarCarteira $criarCarteira): User
    {
        $user = User::create([
            'name' => $nome,
            'email' => $email,
            'password' => Hash::make('Avaliacao#2026'),
        ]);
        $criarCarteira->handle($user);

        return $user;
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }
}
