# 0001 · PHP 7.4 e Laravel 8

Substituída pela [0006](0006-php84-laravel13.md).

## Contexto

Muitos sistemas PHP em produção ainda rodam 7.4, e o projeto foi feito para caber nesse ambiente. O Laravel 8 é a última versão que roda nele. Os dois estão sem suporte (PHP desde nov/2022, Laravel desde jan/2023). Por isso o Composer bloqueia a instalação: há avisos de segurança no Laravel 8 sem correção na série 8.

## Decisão

Ficar no PHP 7.4 com Laravel 8 e liberar cada aviso pelo ID no `composer.json` (`config.policy.advisories.ignore-id`), com o motivo, em vez de desligar a verificação toda.

| Pacote | Aviso | Afeta o projeto? |
|---|---|---|
| laravel/framework | bypass de autenticação no `TokenGuard` | Não. O login é por sessão; não há API com token. |
| laravel/framework | injeção de CRLF na regra `email` | Sim. A regra `email` aceita `\r\n` em alguns formatos (entre aspas, em comentário, antes do `@`). O cadastro recusa com `not_regex:/[\r\n]/`; o `CadastroTest` cobre os três casos. |
| laravel/framework | URL assinada temporária | Não. O projeto não usa URL assinada. |
| laravel/framework | validação de arquivo | Não. Não há upload. |
| laravel/framework | XSS na página de debug | Não em produção (`APP_DEBUG=false`). |
| laravel/framework | injeção de saída no `Concurrency` | Não. O componente só existe a partir do Laravel 11. |
| league/flysystem 1.x | normalização de caminho | Não. Nenhum caminho de arquivo vem do usuário. |
| composer/composer | vários | Só em desenvolvimento (vem com o Larastan); fica fora da imagem `prod`. |

## Consequências

Cada funcionalidade nova tem que ser conferida contra essa tabela. Se um dia entrar upload, por exemplo, o aviso de validação de arquivo passa a valer.

Para produção, o caminho é atualizar para uma versão com suporte (Laravel 12, PHP 8.3). Muda pouco no código: as constantes de tipo e status viram `enum`, o `Money` ganha `readonly`, o `/health` vira a rota nativa `/up` e o Mix vira Vite.
