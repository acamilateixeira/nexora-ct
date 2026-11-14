<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $operacao_id
 * @property int $solicitada_por
 * @property string $motivo
 * @property int $valor_bloqueado
 * @property string $status
 * @property string|null $motivo_recusa
 * @property \Illuminate\Support\Carbon|null $respondida_em
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Operacao $operacao
 * @property-read User $solicitante
 */
class SolicitacaoEstorno extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_ACEITA = 'aceita';
    public const STATUS_RECUSADA = 'recusada';

    protected $table = 'solicitacoes_estorno';

    protected $fillable = ['operacao_id', 'solicitada_por', 'motivo', 'valor_bloqueado', 'status', 'motivo_recusa', 'respondida_em'];

    protected $casts = [
        'respondida_em' => 'datetime',
    ];

    public function estaPendente(): bool
    {
        return $this->status === self::STATUS_PENDENTE;
    }

    /** @return BelongsTo<Operacao, $this> */
    public function operacao(): BelongsTo
    {
        return $this->belongsTo(Operacao::class);
    }

    /** @return BelongsTo<User, $this> */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por');
    }
}
