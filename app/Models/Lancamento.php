<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $operacao_id
 * @property int $carteira_id
 * @property Money $valor
 * @property Money $saldo_apos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Operacao $operacao
 * @property-read Carteira $carteira
 */
class Lancamento extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['operacao_id', 'carteira_id', 'valor', 'saldo_apos'];

    protected $casts = [
        'valor' => MoneyCast::class,
        'saldo_apos' => MoneyCast::class,
    ];

    public function titulo(): string
    {
        $operacao = $this->operacao;
        $entrada = ! $this->valor->isNegative();

        if ($operacao->tipo === Operacao::TIPO_DEPOSITO) {
            return 'Depósito';
        }

        if ($operacao->tipo === Operacao::TIPO_TRANSFERENCIA) {
            return $entrada ? 'Transferência recebida' : 'Transferência enviada';
        }

        if ($operacao->estorna && $operacao->estorna->tipo === Operacao::TIPO_DEPOSITO) {
            return 'Estorno de depósito';
        }

        if ($operacao->operador !== null) {
            return $entrada ? 'Estorno recebido' : 'Estorno debitado';
        }

        return $entrada ? 'Devolução recebida' : 'Devolução enviada';
    }

    public function contraparte(): ?User
    {
        $operacao = $this->operacao;
        $carteira = $this->valor->isNegative() ? $operacao->destino : $operacao->origem;

        return $carteira && $carteira->id !== $this->carteira_id ? $carteira->user : null;
    }

    /** @return BelongsTo<Operacao, $this> */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    /** @return BelongsTo<Carteira, $this> */
    public function carteira(): BelongsTo
    {
        return $this->belongsTo(Carteira::class);
    }
}
