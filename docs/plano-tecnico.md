# Plano técnico

Como o projeto foi pensado e o que falta fazer. O enunciado está em [desafio.md](desafio.md).

## Stack

PHP 8.4, Laravel 13, MySQL 8.4, Docker (nginx + PHP-FPM + MySQL), PHPUnit, Larastan e PHP-CS-Fixer (PSR-12). Telas em Blade; cadastro e login partiram do Laravel Breeze (Tailwind e Alpine, com build pelo Vite), e JavaScript puro no que é do projeto. CI no GitHub Actions.

Por que PHP 8.4 e Laravel 13: [ADR 0006](adr/0006-php84-laravel13.md), que substitui a [ADR 0001](adr/0001-php74-laravel8.md). Por que não tem deploy: [ADR 0002](adr/0002-sem-nuvem.md). Livro-razão e estorno: [ADR 0003](adr/0003-livro-razao-e-estorno.md). Travas e idempotência: [ADR 0004](adr/0004-concorrencia-e-idempotencia.md).

## Nomes

O que é do Laravel fica em inglês (`User`, `Controller`, `Request`, `created_at`), e o domínio fica em português (`Carteira`, `Operacao`, `Lancamento`, `saldo`, `estorno`). O `Money` é um utilitário genérico, como o `Carbon`, e também fica em inglês.

## Estrutura

Só pastas que o Laravel cria ou que os pacotes oficiais usam:

```
app/
  Actions/Carteira/      CriarCarteira, Depositar, Transferir, Estornar, DevolverTransferencia,
                         SolicitarEstorno, RecusarEstorno, ConsultarExtrato, Conciliar, Idempotency
  Casts/MoneyCast.php
  Console/Commands/      carteira:conciliar, carteira:estornar
  Exceptions/Carteira/   erros de negócio, cada um com render() e report()
  Http/Controllers/      Dashboard, Deposito, Transferencia, Operacao
  Http/Requests/         validação e autorização de cada formulário
  Models/                User, Carteira, Operacao, Lancamento, SolicitacaoEstorno
  Policies/OperacaoPolicy.php
  Rules/UnpredictablePassword.php
  ValueObjects/          Money, Periodo, CursorDoExtrato
```

O controller recebe a requisição, saneia o que vem da URL e chama a action. A action abre a transação, trava as carteiras e grava. A regra do saldo fica no model `Carteira` (`creditar`, `debitar`, `debitarMesmoSemSaldo`), e assim vale igual para a tela e para o comando do terminal. No banco, os triggers repetem as invariantes do livro-razão como última barreira (ADR 0005).

Não criei repositórios nem entidades separadas dos models: o Eloquent já resolve, e isso só dobraria o número de classes. As actions recebem classes concretas, então não afirmo inversão de dependência (o D do SOLID).

## Modelagem

**carteiras**: `user_id` (único) e `saldo` (bigint, em centavos, pode ficar negativo). O saldo só muda pelo trigger do lançamento.

**operacoes**: `uuid` (o que aparece na URL), `tipo` (`deposito`, `transferencia` ou `estorno`), `status` (`concluida` ou `estornada`), `valor`, `carteira_origem_id`, `carteira_destino_id`, `estorna_id`, `criada_por` (nulo quando é o operador), `idempotency_key`, `request_hash` e `motivo`.

**lancamentos**: `operacao_id`, `carteira_id`, `valor` (positivo é crédito, negativo é débito), `saldo_apos` e `created_at`. Lançamento não se edita. Índice `(carteira_id, created_at, id, valor)` para o extrato por período.

**solicitacoes_estorno**: `operacao_id` (única), `solicitada_por`, `motivo`, `status` (`pendente`, `aceita` ou `recusada`), `motivo_recusa` e `respondida_em`.

Restrições no banco (`CHECK` e `UNIQUE`):
- valor maior que zero;
- tipo e status válidos;
- depósito só tem destino;
- transferência tem origem e destino, e eles são diferentes;
- `estorna_id` preenchido só no estorno, e no máximo um estorno por operação;
- uma carteira por usuário;
- idempotency key única por pessoa.

As FKs não têm cascade: o MySQL não aceita `ON DELETE` em coluna usada num `CHECK`, e operação financeira não deve sumir em cascata.

O usuário `app` do banco só tem `SELECT` e `INSERT` em `lancamentos` e, em `carteiras`, só pode alterar `bloqueado` e `updated_at` (`docker/mysql/grants.sql`). Quem grava o saldo é o trigger do lançamento, que roda com as permissões do `migrator`. Além disso, triggers valem para qualquer usuário: lançamento e operação não mudam nem somem, o lançamento confere carteira, sinal, valor e continuidade do saldo, e só o estorno do atendimento deixa saldo negativo ([ADR 0005](adr/0005-regras-no-banco-estorno-e-extrato.md)).

## Regras

**Depósito** soma ao saldo, mesmo quando o saldo está negativo (-50 + 100 = 50).

**Transferência** trava as duas carteiras em ordem crescente de id, para não dar deadlock entre A→B e B→A. Só debita se houver saldo; senão lança `SaldoInsuficiente`. Grava a operação, um lançamento negativo e um positivo, tudo numa transação só. `DB::transaction($fn, 3)` tenta de novo em caso de deadlock.

**Idempotência.** Cada formulário leva uma idempotency key gerada quando a tela abre. A key é conferida depois de travar a carteira: se dois cliques chegam juntos, o segundo espera a trava e já encontra a operação gravada. A busca também é feita com trava (`lockForUpdate`), porque no `REPEATABLE READ` do MySQL uma leitura comum poderia enxergar uma foto antiga. O `UNIQUE` é a garantia final. A mesma key com outro valor ou outro destino dá erro.

**Estorno** é uma operação nova, que espelha a original e aponta para ela. A original passa a `estornada`, e nenhum lançamento é apagado. Quem pode estornar:
- uma transferência: quem recebeu pode devolver, mesmo ficando negativo; quem enviou contesta pela tela, o valor fica bloqueado na carteira de quem recebeu, e o atendimento aprova (`carteira:estornar`) ou nega (`carteira:negar-estorno`), liberando o bloqueio;
- um depósito ou qualquer inconsistência: o atendimento, pelo `carteira:estornar {uuid} --motivo= --operador=`. Esse estorno não confere saldo, então pode deixar a carteira negativa, e o próximo depósito soma.

Estorno não se estorna.

**Conciliação.** O `carteira:conciliar`, agendado, só detecta: saldo diferente da soma dos lançamentos, ou operação sem os lançamentos esperados. Ele loga `CRITICAL` e não corrige nada sozinho. A correção é humana, pelo `carteira:estornar`, depois de entender a causa.

## Segurança

Senha com bcrypt e regra de força (composição mais `UnpredictablePassword`: sem sequência, repetição, palavra comum ou dado pessoal), CSRF, sessão regenerada no login, `OperacaoPolicy` (cada pessoa só vê e estorna o que é dela), uuid na URL, rate limit no login e nas operações, `saldo` fora do `$fillable` e Blade escapando tudo. O cookie de sessão é `HttpOnly` e `SameSite=Lax`, e `Secure` em produção. Sessão e cache ficam no banco. Headers `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` e `Cache-Control: no-store` em toda resposta; a página restaurada pelo cache de navegação recarrega. Consulta de senha vazada (`uncompromised()`, Have I Been Pwned) ficou de fora: depende de serviço externo no cadastro. Sem CSP por enquanto: o Alpine do Breeze precisa de `unsafe-eval`.

O formulário de transferência diz se um e-mail tem conta; o controle é exigir login e limitar a 10 operações por minuto ([ADR 0004](adr/0004-concorrencia-e-idempotencia.md)). A recuperação de senha, que não exige login, responde igual para e-mail com e sem conta. O cadastro também diz que o e-mail existe, como qualquer cadastro.

O nginx recusa corpo acima de 32 KB: os formulários têm poucos bytes, não há upload, e um input maior nem caberia na sessão para voltar ao formulário. Nome com caractere invisível ou de controle (`\p{C}`) é recusado, porque o U+202E inverte o texto e enganaria a tela de revisão. As telas de verificação de e-mail e de confirmar senha do Breeze foram removidas: nenhuma rota usava.

## Observabilidade

Logs em JSON no stderr com `request_id`, um log para cada operação concluída, `/health` e `/ready`, e a conciliação agendada.

## Testes

Rodam no MySQL, porque SQLite não trava linha. Além dos testes de cada regra:
- ao fim de cada teste, o saldo de toda carteira bate com a soma dos lançamentos;
- uma falha forçada no meio da transferência não grava nada;
- duas transferências simultâneas, em dois processos, não gastam o mesmo saldo;
- a mesma key enviada duas vezes ao mesmo tempo gera uma operação só;
- dois estornos simultâneos da mesma operação: só um passa.

Os testes de concorrência não usam `RefreshDatabase`, porque a transação do teste esconderia os dados do outro processo.

## Ordem

1. ~~Ambiente, CI, Money~~
2. ~~Tabelas e regra do saldo~~
3. ~~Cadastro e login~~
4. ~~Depósito~~
5. ~~Transferência~~
6. ~~Estorno e `OperacaoPolicy`~~
7. ~~`carteira:conciliar` e `carteira:estornar`~~
8. ~~Testes de integridade e concorrência~~
9. ~~Rate limit, headers de segurança e `request_id`~~
10. ~~Seed de demonstração e roteiro no README~~
