<?php

declare(strict_types=1);

namespace App\Actions\Carteira;

use App\Models\Carteira;
use App\Models\Lancamento;
use App\ValueObjects\Money;
use App\ValueObjects\Periodo;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultarExtrato
{
    public const POR_PAGINA = 20;

    public const TIPOS = ['todos', 'entradas', 'saidas'];

    /** @return array{lancamentos: CursorPaginator<int, Lancamento>, quantidade: int, entradas: Money, saidas: Money} */
    public function handle(Carteira $carteira, Periodo $periodo, string $tipo): array
    {
        $totais = (array) $this->noPeriodo($carteira, $periodo)
            ->toBase()
            ->selectRaw('COUNT(*) AS quantidade')
            ->selectRaw('COALESCE(SUM(CASE WHEN valor > 0 THEN valor END), 0) AS entradas')
            ->selectRaw('COALESCE(SUM(CASE WHEN valor < 0 THEN valor END), 0) AS saidas')
            ->first();

        $lancamentos = $this->noPeriodo($carteira, $periodo)
            ->when($tipo === 'entradas', function ($query) {
                $query->where('valor', '>', 0);
            })
            ->when($tipo === 'saidas', function ($query) {
                $query->where('valor', '<', 0);
            })
            ->with(['operacao.origem.user', 'operacao.destino.user', 'operacao.estorna', 'operacao.solicitacaoEstorno'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::POR_PAGINA)
            ->withQueryString();

        return [
            'lancamentos' => $lancamentos,
            'quantidade' => (int) ($totais['quantidade'] ?? 0),
            'entradas' => Money::ofCents((int) ($totais['entradas'] ?? 0)),
            'saidas' => Money::ofCents((int) ($totais['saidas'] ?? 0)),
        ];
    }

    /** @return HasMany<Lancamento, Carteira> */
    private function noPeriodo(Carteira $carteira, Periodo $periodo): HasMany
    {
        return $carteira->lancamentos()->whereBetween('created_at', [
            $periodo->inicio()->utc(),
            $periodo->fim()->utc(),
        ]);
    }
}
