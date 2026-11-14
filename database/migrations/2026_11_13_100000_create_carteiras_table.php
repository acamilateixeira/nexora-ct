<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarteirasTable extends Migration
{
    public function up(): void
    {
        Schema::create('carteiras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');

            $table->bigInteger('saldo')->default(0);
            $table->char('moeda', 3)->default('BRL');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carteiras');
    }
}
