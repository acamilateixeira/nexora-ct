# 0006 · PHP 8.4 e Laravel 13

Substitui a [0001](0001-php74-laravel8.md).

## Contexto

O PHP 7.4 não era exigência do desafio. Ficar nele obrigava a liberar 16 avisos de segurança no `composer.json`, e cada aviso novo pedia outra análise (saiu um em 13/11/2026). PHP 7.4 e Laravel 8 não recebem mais correção, e o MySQL 8.0 saiu de suporte em abril de 2026.

## Decisão

PHP 8.4, Laravel 13, MySQL 8.4 (LTS), nginx 1.28, Node 24 e Vite. Nenhuma exceção de aviso no `composer.json`, e o CI roda `composer audit`: aviso novo quebra o build.

O que mudou no código:

- Estrutura do Laravel 11+: `bootstrap/app.php` no lugar dos Kernels, do `Handler` e dos providers. Os middlewares que eram cópia do padrão saíram; ficaram `AssignRequestId` (no grupo `web`, logo depois da sessão) e `SecurityHeaders` (por fora de tudo). O agendamento foi para `routes/console.php`.
- Em `config/` ficaram só os arquivos com alguma mudança: `app`, `database`, `logging`, `session` e `carteira`.
- `password_resets` virou `password_reset_tokens`, o nome do Laravel 10+. Sem produção, a migração original foi editada em vez de ganhar uma de renomear.
- Os controllers recebem quem está logado por `#[CurrentUser]`, e a carteira vem de `User::carteiraOuFalha()`: sem carteira é 404, não erro de `null`.
- PHPUnit 12 com `#[DataProvider]`; Larastan 3 no nível 8, com os generics das relações.
- Mix virou Vite. O Tailwind ficou na 3.4, porque a 4 muda nomes de classes. Saiu o `bootstrap.js` do esqueleto, que carregava `axios` e `lodash` sem uso.
- O Breeze saiu do `composer.json`: as telas de login já estavam no projeto.

## Consequências

`composer audit` e `npm audit` sem avisos. O domínio (livro-razão, triggers, travas, idempotência) não mudou, e os mesmos testes passam.

Fica para depois: `enum` para tipo e status da operação e `readonly` no `Money`.
