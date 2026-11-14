SHELL := /bin/sh
export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

COMPOSE   := docker compose
APP       := $(COMPOSE) exec -T app
ARTISAN   := $(APP) php artisan
DB_ROOT   := $(COMPOSE) exec -T db mysql -uroot -proot-local-secret

.DEFAULT_GOAL := help
.PHONY: help up down restart env build start install key migrate grants fresh seed assets test lint lint-fix stan phpunit logs shell db check-grants conciliar estornar negar-estorno scheduler

help: ## lista os comandos
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  make %-10s %s\n", $$1, $$2}'

up: env build start install key migrate seed assets scheduler ## prepara tudo e sobe o ambiente
	@echo ""
	@echo "Pronto: http://localhost:$${APP_PORT:-8080}  (saúde: /health e /ready)"

down: ## para os containers (mantém o banco)
	$(COMPOSE) down

restart: down start ## reinicia os containers

env: ## cria .env e .env.testing a partir dos exemplos, se não existirem
	@test -f .env || cp .env.example .env
	@test -f .env.testing || cp .env.testing.example .env.testing

build:
	$(COMPOSE) build

start:
	$(COMPOSE) up -d --wait app web db

install: ## instala as dependências PHP
	$(APP) composer install --no-interaction --prefer-dist

key:
	@grep -q '^APP_KEY=base64' .env || $(ARTISAN) key:generate --ansi

migrate: ## roda as migrações com o usuário migrator e aplica as permissões do usuário app
	$(ARTISAN) migrate --database=migrator --force
	$(MAKE) grants

grants:
	$(DB_ROOT) < docker/mysql/grants.sql

DB_APP := $(COMPOSE) exec -T db mysql -uapp -papp-local-secret wallet

check-grants: ## confere que o usuário app não mexe no saldo nem altera ou apaga lançamentos
	@for sql in "UPDATE lancamentos SET valor = 1" "DELETE FROM lancamentos" "DROP TABLE lancamentos" \
		"SET @lancamento_na_carteira = 1; UPDATE carteiras SET saldo = saldo + 100 WHERE id = 1"; do \
		if $(DB_APP) -e "$$sql" >/dev/null 2>&1; then echo "erro: o usuário app conseguiu rodar: $$sql"; exit 1; fi; \
		echo "recusado: $$sql"; \
	done
	@for sql in "SELECT COUNT(*) FROM lancamentos" "SELECT id FROM carteiras LIMIT 1 FOR UPDATE" \
		"UPDATE carteiras SET bloqueado = bloqueado WHERE id = 0"; do \
		$(DB_APP) -e "$$sql" >/dev/null || { echo "erro: o usuário app não conseguiu rodar: $$sql"; exit 1; }; \
		echo "permitido: $$sql"; \
	done

fresh: ## recria o banco do zero, com os dados de demonstração
	$(ARTISAN) migrate:fresh --database=migrator --seed --force
	$(MAKE) grants

seed: ## cria as contas de demonstração (se ainda não existirem)
	$(ARTISAN) db:seed --database=migrator --force

assets: ## compila CSS e JS
	$(COMPOSE) run --rm node sh -c "if [ -f package-lock.json ]; then npm ci; else npm install; fi && npm run build"

test: lint stan phpunit ## estilo + análise estática + testes

lint: ## confere o estilo (PSR-12)
	$(APP) composer lint

lint-fix: ## corrige o estilo
	$(APP) composer lint:fix

stan: ## análise estática (Larastan)
	$(APP) composer stan

phpunit: ## testes unitários e de integração
	$(APP) php artisan test

logs: ## acompanha os logs (JSON)
	$(COMPOSE) logs -f app web scheduler

scheduler:
	$(COMPOSE) up -d scheduler

conciliar: ## confere se os saldos batem com os lançamentos
	$(ARTISAN) carteira:conciliar

estornar: ## estorna uma operação: make estornar uuid=... motivo="..." operador="..."
	$(ARTISAN) carteira:estornar $(uuid) --motivo="$(motivo)" --operador="$(operador)"

negar-estorno: ## nega a contestação de uma transferência: make negar-estorno uuid=... motivo="..." operador="..."
	$(ARTISAN) carteira:negar-estorno $(uuid) --motivo="$(motivo)" --operador="$(operador)"

shell: ## terminal dentro do container da aplicação
	$(COMPOSE) exec app sh

db: ## cliente MySQL como usuário app
	$(COMPOSE) exec db mysql --default-character-set=utf8mb4 -uapp -papp-local-secret wallet
