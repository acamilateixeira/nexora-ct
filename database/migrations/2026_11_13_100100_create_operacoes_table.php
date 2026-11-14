<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateOperacoesTable extends Migration
{
    public function up(): void
    {
        Schema::create('operacoes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tipo', 16);
            $table->string('status', 16);
            $table->bigInteger('valor');
            $table->foreignId('carteira_origem_id')->nullable()->constrained('carteiras');
            $table->foreignId('carteira_destino_id')->nullable()->constrained('carteiras');
            $table->foreignId('estorna_id')->nullable()->unique()->constrained('operacoes');

            $table->foreignId('criada_por')->nullable()->constrained('users');
            $table->uuid('idempotency_key')->nullable();
            $table->char('request_hash', 64)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->timestamps();

            $table->unique(['criada_por', 'idempotency_key']);
        });

        $checks = [
            'operacoes_tipo_check' => "tipo IN ('deposito', 'transferencia', 'estorno')",
            'operacoes_status_check' => "status IN ('concluida', 'estornada')",
            'operacoes_valor_check' => 'valor > 0',
            'operacoes_carteiras_check' => 'carteira_origem_id IS NOT NULL OR carteira_destino_id IS NOT NULL',
            'operacoes_mesma_carteira_check' => 'carteira_origem_id IS NULL OR carteira_destino_id IS NULL OR carteira_origem_id <> carteira_destino_id',
            'operacoes_deposito_check' => "tipo <> 'deposito' OR (carteira_origem_id IS NULL AND carteira_destino_id IS NOT NULL)",
            'operacoes_transferencia_check' => "tipo <> 'transferencia' OR (carteira_origem_id IS NOT NULL AND carteira_destino_id IS NOT NULL)",
            'operacoes_estorno_check' => "(tipo = 'estorno') = (estorna_id IS NOT NULL)",
        ];

        foreach ($checks as $nome => $condicao) {
            DB::statement("ALTER TABLE operacoes ADD CONSTRAINT {$nome} CHECK ({$condicao})");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operacoes');
    }
}
