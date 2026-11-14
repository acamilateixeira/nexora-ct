# 0002 · Sem deploy em nuvem

## Contexto

Publicar numa nuvem seria um diferencial. O desafio pede a aplicação rodando no dia da avaliação, mas não pede que ela esteja publicada.

## Decisão

Não publicar. O projeto roda inteiro com `make up` e `make test`, e o CI prova isso a cada push.

Publicar exigiria banco gerenciado, registro de imagem, segredos e monitoramento, com custo mensal, para uma aplicação de demonstração. Também deixaria um sistema financeiro de teste exposto na internet sem necessidade. Preferi gastar o tempo na integridade do dinheiro e nos testes.

## Consequências

O projeto não mostra deploy em nuvem.

O que já ajuda numa publicação futura: imagem `prod` separada, logs em JSON no stderr, `/health` e `/ready`, sessão e cache no banco (funciona com mais de uma réplica) e usuários do banco com permissão mínima. Ainda faltaria: pipeline de deploy com migração, segredos num cofre, backup do banco, TLS e alertas.
