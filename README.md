# Nexora Core

[![CI](https://github.com/acamilateixeira/nexora-ct/actions/workflows/ci.yml/badge.svg)](https://github.com/acamilateixeira/nexora-ct/actions/workflows/ci.yml)

Carteira digital em PHP 8.4, Laravel 13 e MySQL 8.4. Tem cadastro, login, depósito, transferência entre usuários, devolução, contestação com bloqueio do valor e extrato. Foi feita para um desafio técnico ([enunciado](docs/desafio.md)).

## Como rodar

Precisa de Git, Docker com Compose v2 e `make`, e das portas `8080` (aplicação) e `33060` (MySQL) livres. PHP, Composer, Node e MySQL rodam nos containers; nada disso precisa estar instalado. No Windows, use o WSL2 e clone o projeto dentro dele, não em `/mnt/c`.

O Compose é só para rodar na máquina: a senha de root do MySQL é fixa (`root-local-secret`) e a porta do banco abre apenas em `127.0.0.1`.

```bash
git clone https://github.com/acamilateixeira/nexora-ct.git
cd nexora-ct
make up
```

O `make up` cria o `.env`, sobe os containers, instala as dependências, roda as migrações, aplica as permissões do banco, cria os dados de demonstração, compila os assets e liga o agendador. A primeira vez leva alguns minutos por causa das imagens.

| | |
|---|---|
| Aplicação | http://localhost:8080 |
| Saúde do processo | http://localhost:8080/health |
| Banco respondendo | http://localhost:8080/ready |

`make help` lista todos os comandos.

## Contas de demonstração

Todas com a senha `Avaliacao#2026`.

| Conta | Saldo |
|---|---|
| `helena@exemplo.com` | R$ 3.200,00 |
| `marina@exemplo.com` | R$ 1.500,00 |
| `rafael@exemplo.com` | -R$ 150,00 |

Os três têm, nas últimas duas semanas, cada situação dos dois lados (Helena → Marina, Marina → Rafael, Rafael → Helena): transferência devolvida, contestação aprovada, contestação negada, contestação em análise com valor bloqueado e depósito em duplicidade estornado pelo atendimento. O Rafael fica negativo porque já tinha gastado quando o depósito dele foi estornado.

## Roteiro de avaliação

**Pela tela**

1. Entre com o Rafael e faça um depósito: ele soma ao saldo negativo.
2. Transfira para outra conta pelo e-mail; a revisão mostra quem recebe e o saldo depois.
3. Entre com quem recebeu e devolva a transferência.
4. Volte para quem enviou, faça outra transferência e conteste: o valor fica bloqueado na carteira de quem recebeu, e o disponível dela cai.

**Pelo atendimento** (comandos de terminal; não há backoffice com login próprio)

O uuid está no endereço da operação: `/operacoes/<uuid>`.

```bash
make estornar uuid=<uuid> motivo="Contestação procedente" operador="atendimento"
make negar-estorno uuid=<uuid> motivo="Contestação improcedente" operador="atendimento"
```

`estornar` vale para depósito e transferência, com ou sem contestação; `negar-estorno` responde uma contestação em análise e libera o bloqueio.

**Pelo banco**

```bash
make check-grants   # o usuário da aplicação não altera saldo nem lançamentos
make conciliar      # confere se cada saldo bate com a soma dos lançamentos
make db             # cliente MySQL com o usuário da aplicação, para tentar adulterar à mão
```

**Testes**

```bash
make test      # tudo: estilo, análise estática e testes
make lint      # PSR-12
make stan      # Larastan nível 8
make phpunit   # testes unitários e de integração
```

## Como a carteira funciona

**Depósito** soma ao saldo, inclusive quando ele está negativo.

**Transferência** é feita por e-mail, em dois passos: a revisão mostra o nome abreviado de quem recebe e o saldo depois. Só sai com saldo disponível.

**Devolução.** Quem recebeu pode devolver uma transferência, mesmo ficando negativo.

**Contestação.** Quem enviou contesta pela tela. O valor fica bloqueado na carteira de quem recebeu, até o quanto houver disponível, e o atendimento aprova (`make estornar`) ou nega (`make negar-estorno`).

**Estorno** é sempre uma operação nova, que aparece no extrato dos dois lados. Nada é apagado ou alterado. Depósito só é estornado pelo atendimento.

**Extrato** com período de 7, 30 ou 90 dias ou datas escolhidas (até 90), filtro de entradas e saídas, totais do período e saldo de cada dia.

## Decisões

- **Dinheiro em centavos**, em `bigint` e no value object `Money`. Float não representa 0,10 exato.
- **Livro-razão.** Cada movimento gera um lançamento imutável com o saldo logo depois. O saldo da carteira é uma cópia para leitura rápida, e a conciliação (`make conciliar`, também agendada) confere que os dois batem ([ADR 0003](docs/adr/0003-livro-razao-e-estorno.md)).
- **Regras também no banco.** Triggers recusam alterar ou apagar lançamento e operação, saldo mudado sem lançamento e transferência sem saldo disponível (saldo menos o bloqueado). A aplicação usa um usuário MySQL que não altera a coluna `saldo` nem altera ou apaga lançamentos: o saldo só muda pelo trigger, que roda com as permissões de quem o criou. `make check-grants` mostra isso ([ADR 0005](docs/adr/0005-regras-no-banco-estorno-e-extrato.md)).
- **Concorrência e idempotência.** As carteiras são travadas em ordem de id, e cada formulário leva uma idempotency key: o mesmo envio duas vezes gera uma operação só ([ADR 0004](docs/adr/0004-concorrencia-e-idempotencia.md)).
- **Extrato com volume.** Paginação por cursor e índice que cobre a consulta. Com 1 milhão de lançamentos numa carteira, qualquer página fica abaixo de 100 ms.
- **Segurança.** Senha forte (sem sequências, repetições, palavras comuns ou dados pessoais), sessão de 15 minutos, `Cache-Control: no-store`, recuperação de senha sem revelar quem tem conta, limite de requisições e headers de segurança.
- **PHP 8.4 e Laravel 13**, versões com suporte, sem nenhum aviso de segurança liberado no Composer; o CI roda `composer audit` ([ADR 0006](docs/adr/0006-php84-laravel13.md)). Sem deploy em nuvem: `make up` basta para a avaliação ([ADR 0002](docs/adr/0002-sem-nuvem.md)).

O desenho completo está no [plano técnico](docs/plano-tecnico.md).

## Testes

Rodam no MySQL, porque travas, `CHECK` e triggers fazem parte das regras. Cobrem os fluxos pela rota, as regras do `Money`, do período e da senha, adulteração direta no banco por SQL, entradas forjadas (campos como array, cursor e URL), páginas de erro e concorrência com processos PHP separados ao mesmo tempo.

## Observabilidade

Logs em JSON no stderr, cada um com `request_id` e `user_id`; o `X-Request-Id` volta na resposta. Cada operação de dinheiro gera um evento de log, e a conciliação registra `CRITICAL` quando encontra divergência. `/health` responde se o processo está de pé e `/ready` se o banco responde. `make logs` acompanha tudo.

## Problemas comuns

- **Porta ocupada.** Defina `APP_PORT=8081` (ou `DB_PORT_HOST` para o MySQL) no `.env` e rode `make restart`.
- **A aplicação não responde.** `docker compose ps` mostra os containers; `make logs` mostra os erros.
- **Voltar aos dados de demonstração.** `make fresh` apaga o banco e recria tudo. `make down` só para os containers e mantém os dados.

## Autora

Camila Teixeira · [LinkedIn](https://www.linkedin.com/in/acamilateixeira) · [GitHub](https://github.com/acamilateixeira)

Todos os direitos reservados.
