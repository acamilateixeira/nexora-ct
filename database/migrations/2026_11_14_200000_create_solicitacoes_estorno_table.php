<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSolicitacoesEstornoTable extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_estorno', function (Blueprint $table) {
            $table->id();

            $table->foreignId('operacao_id')->unique()->constrained('operacoes');
            $table->foreignId('solicitada_por')->constrained('users');
            $table->string('motivo', 255);
            $table->string('status', 16);
            $table->string('motivo_recusa', 255)->nullable();
            $table->timestamp('respondida_em')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        DB::statement("ALTER TABLE solicitacoes_estorno ADD CONSTRAINT solicitacoes_estorno_status_check CHECK (status IN ('pendente', 'aceita', 'recusada'))");
        DB::statement("ALTER TABLE solicitacoes_estorno ADD CONSTRAINT solicitacoes_estorno_resposta_check CHECK ((status = 'pendente') = (respondida_em IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_estorno');
    }
}
