-- roda depois das migrações (o MySQL só concede em tabela que existe)
-- SELECT ... FOR UPDATE precisa de SELECT e de UPDATE, DELETE ou LOCK TABLES na tabela

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'app'@'%';

-- UPDATE só em colunas não conta para o FOR UPDATE; o LOCK TABLES cobre as carteiras
GRANT LOCK TABLES ON wallet.* TO 'app'@'%';

GRANT SELECT, INSERT, UPDATE ON wallet.users TO 'app'@'%';
GRANT SELECT, INSERT, DELETE ON wallet.password_reset_tokens TO 'app'@'%';

-- carteiras: o saldo só muda pelo trigger do lançamento, que roda com as permissões do migrator.
-- A marca @lancamento_na_carteira é variável de sessão; sem UPDATE em saldo, definir a marca não adianta.
GRANT SELECT, INSERT, UPDATE (bloqueado, updated_at) ON wallet.carteiras TO 'app'@'%';
GRANT SELECT, INSERT, UPDATE ON wallet.operacoes TO 'app'@'%';
-- lançamentos: sem UPDATE e DELETE
GRANT SELECT, INSERT ON wallet.lancamentos TO 'app'@'%';
GRANT SELECT, INSERT, UPDATE ON wallet.solicitacoes_estorno TO 'app'@'%';

GRANT SELECT, INSERT, UPDATE, DELETE ON wallet.sessions TO 'app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON wallet.cache TO 'app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON wallet.cache_locks TO 'app'@'%';

FLUSH PRIVILEGES;
