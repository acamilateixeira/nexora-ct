<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BloqueioCautelar extends Migration
{
    public function up(): void
    {
        Schema::table('carteiras', function (Blueprint $table) {
            $table->bigInteger('bloqueado')->default(0)->after('saldo');
        });
        Schema::table('solicitacoes_estorno', function (Blueprint $table) {
            $table->bigInteger('valor_bloqueado')->default(0)->after('motivo');
        });

        DB::statement('ALTER TABLE carteiras ADD CONSTRAINT carteiras_bloqueado_check CHECK (bloqueado >= 0)');
        DB::statement('ALTER TABLE solicitacoes_estorno ADD CONSTRAINT solicitacoes_estorno_bloqueado_check CHECK (valor_bloqueado >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE solicitacoes_estorno DROP CHECK solicitacoes_estorno_bloqueado_check');
        DB::statement('ALTER TABLE carteiras DROP CHECK carteiras_bloqueado_check');
        Schema::table('solicitacoes_estorno', function (Blueprint $table) {
            $table->dropColumn('valor_bloqueado');
        });
        Schema::table('carteiras', function (Blueprint $table) {
            $table->dropColumn('bloqueado');
        });
    }
}
