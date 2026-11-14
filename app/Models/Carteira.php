<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Exceptions\Carteira\SaldoInsuficiente;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property Money $saldo
 * @property Money $bloqueado
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Lancamento> $lancamentos
 */
class Carteira extends Model
{
    /** @use HasFactory<\Database\Factories\CarteiraFactory> */
    use HasFactory;

    protected $fillable = ['user_id'];

    protected $attributes = [
        'saldo' => 0,
        'bloqueado' => 0,
    ];

    protected $casts = [
        'saldo' => MoneyCast::class,
        'bloqueado' => MoneyCast::class,
    ];

    public function creditar(Money $valor): void
    {
        $valor->assertPositive();

        $this->saldo = $this->saldo->add($valor);
    }

    public function disponivel(): Money
    {
        return $this->saldo->subtract($this->bloqueado);
    }

    public function disponivelParaTransferir(): Money
    {
        $disponivel = $this->disponivel();

        return $disponivel->isNegative() ? Money::zero() : $disponivel;
    }

    public function bloquearAte(Money $valor): Money
    {
        $disponivel = $this->disponivel();
        $bloquear = $disponivel->isLessThan($valor) ? $disponivel : $valor;
        if (! $bloquear->isPositive()) {
            return Money::zero();
        }

        $this->bloqueado = $this->bloqueado->add($bloquear);

        return $bloquear;
    }

    public function liberar(Money $valor): void
    {
        $this->bloqueado = $this->bloqueado->isLessThan($valor) ? Money::zero() : $this->bloqueado->subtract($valor);
    }

    public function debitar(Money $valor): void
    {
        $valor->assertPositive();

        if ($this->disponivel()->isLessThan($valor)) {
            throw SaldoInsuficiente::paraCarteira($this, $valor);
        }

        $this->saldo = $this->saldo->subtract($valor);
    }

    public function debitarMesmoSemSaldo(Money $valor): void
    {
        $valor->assertPositive();

        $this->saldo = $this->saldo->subtract($valor);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Lancamento, $this> */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }
}
