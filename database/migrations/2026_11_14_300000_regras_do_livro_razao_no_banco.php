<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegrasDoLivroRazaoNoBanco extends Migration
{
    public function up(): void
    {
        Schema::table('lancamentos', function (Blueprint $table) {
            $table->unique(['operacao_id', 'carteira_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER carteiras_saldo_inicial BEFORE INSERT ON carteiras FOR EACH ROW
            BEGIN
                IF NEW.saldo <> 0 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'carteira nasce com saldo zero';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER carteiras_saldo_so_por_lancamento BEFORE UPDATE ON carteiras FOR EACH ROW
            BEGIN
                IF NEW.saldo <> OLD.saldo AND NOT (@lancamento_na_carteira <=> NEW.id) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'saldo só muda por lançamento';
                END IF;
                IF NEW.user_id <> OLD.user_id THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'carteira não troca de dono';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER carteiras_sem_delete BEFORE DELETE ON carteiras FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'carteira não é apagada'
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER lancamentos_confere BEFORE INSERT ON lancamentos FOR EACH ROW
            BEGIN
                DECLARE v_saldo BIGINT;
                DECLARE v_tipo VARCHAR(16);
                DECLARE v_valor BIGINT;
                DECLARE v_origem BIGINT UNSIGNED;
                DECLARE v_destino BIGINT UNSIGNED;
                DECLARE v_operador VARCHAR(100);

                SELECT saldo INTO v_saldo FROM carteiras WHERE id = NEW.carteira_id FOR UPDATE;
                SELECT tipo, valor, carteira_origem_id, carteira_destino_id, operador
                    INTO v_tipo, v_valor, v_origem, v_destino, v_operador
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

                IF NEW.valor < 0 AND NEW.saldo_apos < 0 AND v_tipo <> 'estorno' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'saldo insuficiente';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER lancamentos_atualiza_saldo AFTER INSERT ON lancamentos FOR EACH ROW
            BEGIN
                SET @lancamento_na_carteira = NEW.carteira_id;
                UPDATE carteiras SET saldo = NEW.saldo_apos, updated_at = NOW() WHERE id = NEW.carteira_id;
                SET @lancamento_na_carteira = NULL;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER lancamentos_sem_update BEFORE UPDATE ON lancamentos FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lançamento não é alterado; corrija com estorno'
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER lancamentos_sem_delete BEFORE DELETE ON lancamentos FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'lançamento não é apagado; corrija com estorno'
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER operacoes_imutaveis BEFORE UPDATE ON operacoes FOR EACH ROW
            BEGIN
                IF NOT (NEW.uuid <=> OLD.uuid AND NEW.tipo <=> OLD.tipo AND NEW.valor <=> OLD.valor
                    AND NEW.carteira_origem_id <=> OLD.carteira_origem_id AND NEW.carteira_destino_id <=> OLD.carteira_destino_id
                    AND NEW.estorna_id <=> OLD.estorna_id AND NEW.criada_por <=> OLD.criada_por
                    AND NEW.operador <=> OLD.operador AND NEW.motivo <=> OLD.motivo
                    AND NEW.idempotency_key <=> OLD.idempotency_key AND NEW.request_hash <=> OLD.request_hash
                    AND NEW.created_at <=> OLD.created_at) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operação não é alterada; só o status muda pelo estorno';
                END IF;

                IF NEW.status <> OLD.status THEN
                    IF NOT (OLD.status = 'concluida' AND NEW.status = 'estornada') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'status só vai de concluida para estornada';
                    END IF;
                    IF NOT EXISTS (SELECT 1 FROM operacoes WHERE estorna_id = OLD.id) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operação só fica estornada com o estorno gravado';
                    END IF;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER operacoes_sem_delete BEFORE DELETE ON operacoes FOR EACH ROW
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'operação não é apagada; corrija com estorno'
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER solicitacoes_estorno_resposta BEFORE UPDATE ON solicitacoes_estorno FOR EACH ROW
            BEGIN
                IF OLD.status <> 'pendente' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'solicitação já respondida';
                END IF;
                IF NOT (NEW.operacao_id <=> OLD.operacao_id AND NEW.solicitada_por <=> OLD.solicitada_por AND NEW.motivo <=> OLD.motivo) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'solicitação só muda a resposta';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        foreach ([
            'carteiras_saldo_inicial', 'carteiras_saldo_so_por_lancamento', 'carteiras_sem_delete',
            'lancamentos_confere', 'lancamentos_atualiza_saldo', 'lancamentos_sem_update', 'lancamentos_sem_delete',
            'operacoes_imutaveis', 'operacoes_sem_delete', 'solicitacoes_estorno_resposta',
        ] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        Schema::table('lancamentos', function (Blueprint $table) {
            $table->index('operacao_id', 'lancamentos_operacao_id_fk_index');
        });

        Schema::table('lancamentos', function (Blueprint $table) {
            $table->dropUnique(['operacao_id', 'carteira_id']);
        });
    }
}
