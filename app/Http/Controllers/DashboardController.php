<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Carteira\ConsultarExtrato;
use App\Models\User;
use App\ValueObjects\CursorDoExtrato;
use App\ValueObjects\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ConsultarExtrato $consultar): View
    {
        $carteira = $user->carteiraOuFalha();
        $periodo = Periodo::daQuery($request->query->all(), CarbonImmutable::now(config('app.display_timezone')));
        $tipo = in_array($request->query('tipo'), ConsultarExtrato::TIPOS, true) ? (string) $request->query('tipo') : 'todos';

        if (! CursorDoExtrato::ehValido($request->query('cursor'))) {
            $request->query->remove('cursor');
        }

        return view('dashboard', array_merge($consultar->handle($carteira, $periodo, $tipo), [
            'carteira' => $carteira,
            'periodo' => $periodo,
            'tipo' => $tipo,
        ]));
    }
}
