<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tipo
 * @property string $status
 * @property Money $valor
 * @property int|null $carteira_origem_id
 * @property int|null $carteira_destino_id
 * @property int|null $estorna_id
 * @property int|null $criada_por
 * @property string|null $operador
 * @property string|null $idempotency_key
 * @property string|null $request_hash
 * @property string|null $motivo
 * @property-read Carteira|null $origem
 * @property-read Carteira|null $destino
 * @property-read Operacao|null $estorna
 * @property-read Operacao|null $estorno
 * @property-read User|null $autor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Lancamento> $lancamentos
 */
class Operacao extends Model
{
    public const TIPO_DEPOSITO = 'deposito';
    public const TIPO_TRANSFERENCIA = 'transferencia';
    public const TIPO_ESTORNO = 'estorno';

    public const STATUS_CONCLUIDA = 'concluida';
    public const STATUS_ESTORNADA = 'estornada';

    protected $table = 'operacoes';

    protected $fillable = [
        'tipo',
        'status',
        'valor',
        'carteira_origem_id',
        'carteira_destino_id',
        'estorna_id',
        'criada_por',
        'operador',
        'idempotency_key',
        'request_hash',
        'motivo',
    ];

    protected $casts = [
        'valor' => MoneyCast::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Operacao $operacao) {
            $operacao->uuid = $operacao->uuid ?: (string) Str::uuid();
        });
    }

    public function creditar(Carteira $carteira): Lancamento
    {
        $carteira->creditar($this->valor);

        return $this->lancar($carteira, $this->valor);
    }

    public function debitar(Carteira $carteira): Lancamento
    {
        $carteira->debitar($this->valor);

        return $this->lancar($carteira, $this->valor->negate());
    }

    public function debitarMesmoSemSaldo(Carteira $carteira): Lancamento
    {
        $carteira->debitarMesmoSemSaldo($this->valor);

        return $this->lancar($carteira, $this->valor->negate());
    }

    public function estaEstornada(): bool
    {
        return $this->status === self::STATUS_ESTORNADA;
    }

    public function podeSerEstornada(): bool
    {
        return $this->tipo !== self::TIPO_ESTORNO && ! $this->estaEstornada();
    }

    public function rotulo(): string
    {
        $rotulos = [
            self::TIPO_DEPOSITO => 'Depósito',
            self::TIPO_TRANSFERENCIA => 'Transferência',
            self::TIPO_ESTORNO => 'Estorno',
        ];

        return $rotulos[$this->tipo] ?? $this->tipo;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Carteira, $this> */
    public function origem(): BelongsTo
    {
        return $this->belongsTo(Carteira::class, 'carteira_origem_id');
    }

    /** @return BelongsTo<Carteira, $this> */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Carteira::class, 'carteira_destino_id');
    }

    /** @return BelongsTo<self, $this> */
    public function estorna(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorna_id');
    }

    /** @return HasOne<self, $this> */
    public function estorno(): HasOne
    {
        return $this->hasOne(self::class, 'estorna_id');
    }

    /** @return HasOne<SolicitacaoEstorno, $this> */
    public function solicitacaoEstorno(): HasOne
    {
        return $this->hasOne(SolicitacaoEstorno::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    /** @return HasMany<Lancamento, $this> */
    public function lancamentos(): HasMany
    {
        return $this->hasMany(Lancamento::class);
    }

    private function lancar(Carteira $carteira, Money $valor): Lancamento
    {
        $lancamento = $this->lancamentos()->create([
            'carteira_id' => $carteira->id,
            'valor' => $valor,
            'saldo_apos' => $carteira->saldo,
        ]);

        $carteira->syncOriginalAttribute('saldo');

        return $lancamento;
    }
}
