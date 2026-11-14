<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateLancamentosTable extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacao_id')->constrained('operacoes');
            $table->foreignId('carteira_id')->constrained('carteiras');
            $table->bigInteger('valor');
            $table->bigInteger('saldo_apos');
            $table->timestamp('created_at')->nullable();

            $table->index(['carteira_id', 'id']);
        });

        DB::statement('ALTER TABLE lancamentos ADD CONSTRAINT lancamentos_valor_check CHECK (valor <> 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
}
