<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SaldoDisponivelNoTrigger extends Migration
{
    public function up(): void
    {
        $this->recriar(true);
    }

    public function down(): void
    {
        $this->recriar(false);
    }

    private function recriar(bool $considerarBloqueado): void
    {
        $saldoInsuficiente = $considerarBloqueado
            ? 'NEW.valor < 0 AND v_tipo <> \'estorno\' AND NEW.saldo_apos - v_bloqueado < 0'
            : 'NEW.valor < 0 AND NEW.saldo_apos < 0 AND v_tipo <> \'estorno\'';

        DB::unprepared('DROP TRIGGER IF EXISTS lancamentos_confere');
        DB::unprepared(<<<SQL
            CREATE TRIGGER lancamentos_confere BEFORE INSERT ON lancamentos FOR EACH ROW
            BEGIN
                DECLARE v_saldo BIGINT;
                DECLARE v_bloqueado BIGINT;
                DECLARE v_tipo VARCHAR(16);
                DECLARE v_valor BIGINT;
                DECLARE v_origem BIGINT UNSIGNED;
                DECLARE v_destino BIGINT UNSIGNED;

                SELECT saldo, bloqueado INTO v_saldo, v_bloqueado FROM carteiras WHERE id = NEW.carteira_id FOR UPDATE;
                SELECT tipo, valor, carteira_origem_id, carteira_destino_id
                    INTO v_tipo, v_valor, v_origem, v_destino
                    FROM operacoes WHERE id = NEW.operacao_id;

                IF NEW.carteira_id <=> v_origem THEN
                    IF NEW.valor <> -v_valor THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lançamento de saída com valor diferente da operação';
                    END IF;
                ELSEIF NEW.carteira_id <=> v_destino THEN
                    IF NEW.valor <> v_valor THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lançamento de entrada com valor diferente da operação';
                    END IF;
                ELSE
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'carteira não participa da operação';
                END IF;

                IF NEW.saldo_apos <> v_saldo + NEW.valor THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'saldo_apos não continua o saldo da carteira';
                END IF;

                IF {$saldoInsuficiente} THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'saldo insuficiente';
                END IF;
            END
        SQL);
    }
}
