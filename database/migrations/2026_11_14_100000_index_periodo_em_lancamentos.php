<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class IndexPeriodoEmLancamentos extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->index(['carteira_id', 'created_at', 'id', 'valor'], 'lancamentos_carteira_periodo_index');
        });

        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropIndex(['carteira_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->index(['carteira_id', 'id']);
        });

        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropIndex('lancamentos_carteira_periodo_index');
        });
    }
}
