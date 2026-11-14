<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropMoedaFromCarteirasTable extends Migration
{
    public function up(): void
    {
        Schema::table('carteiras', function (Blueprint $table) {
            $table->dropColumn('moeda');
        });
    }

    public function down(): void
    {
        Schema::table('carteiras', function (Blueprint $table) {
            $table->char('moeda', 3)->default('BRL')->after('saldo');
        });
    }
}
