-- senhas só de desenvolvimento

CREATE DATABASE IF NOT EXISTS wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS wallet_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'migrator'@'%' IDENTIFIED BY 'migrator-local-secret';
GRANT ALL PRIVILEGES ON wallet.* TO 'migrator'@'%';
GRANT ALL PRIVILEGES ON wallet_test.* TO 'migrator'@'%';

-- permissões do app: grants.sql
CREATE USER IF NOT EXISTS 'app'@'%' IDENTIFIED BY 'app-local-secret';

FLUSH PRIVILEGES;
