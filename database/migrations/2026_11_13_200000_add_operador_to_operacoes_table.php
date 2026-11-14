<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOperadorToOperacoesTable extends Migration
{
    public function up(): void
    {
        Schema::table('operacoes', function (Blueprint $table) {
            $table->string('operador', 100)->nullable()->after('criada_por');
        });

        DB::statement('ALTER TABLE operacoes ADD CONSTRAINT operacoes_autor_check CHECK (criada_por IS NOT NULL OR operador IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE operacoes DROP CHECK operacoes_autor_check');

        Schema::table('operacoes', function (Blueprint $table) {
            $table->dropColumn('operador');
        });
    }
}
