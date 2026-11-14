# 0004 · Concorrência e idempotência

## Contexto

Duas transferências da mesma carteira ao mesmo tempo não podem gastar o mesmo saldo. Um clique duplo, ou a rede repetindo o envio, não pode cobrar duas vezes.

## Decisão

**Trava pessimista.** Cada operação abre `DB::transaction` e trava as carteiras envolvidas com `lockForUpdate()` (`SELECT ... FOR UPDATE`), sempre em ordem crescente de id. A ordem fixa evita o deadlock entre A→B e B→A. Se ainda assim o MySQL acusar deadlock, o `DB::transaction($fn, 3)` tenta de novo.

Escolhi trava pessimista em vez de otimista (coluna de versão) porque o conflito aqui é justamente o caso que importa: a segunda transferência tem que esperar e então ver o saldo novo, não falhar e pedir para o usuário repetir.

**Idempotência.** Cada formulário leva uma idempotency key gerada quando a tela abre. A key é conferida depois de travar a carteira: quem chega em segundo espera a trava e encontra a operação já gravada, então recebe a mesma operação em vez de outra. O `UNIQUE (criada_por, idempotency_key)` é a garantia final. A key fica ligada ao conteúdo por um hash; a mesma key com outro valor ou outro destino é recusada.

## Como foi provado

O `ConcorrenciaTest` roda processos PHP separados, cada um com a sua conexão, começando no mesmo instante: cinco transferências de R$ 30,00 com R$ 100,00 de saldo (três passam), A→B e B→A juntos, a mesma key em quatro processos e três estornos da mesma operação. Tirando o `lockForUpdate` da carteira, o primeiro teste falha: as cinco passam.

## Consequências

- Transferências da mesma carteira ficam em fila. Para uma carteira pessoal isso não é gargalo.
- A busca da key também trava. Hoje é defesa: a primeira leitura da transação já é a trava da carteira, e o `REPEATABLE READ` do MySQL só tira a "foto" na primeira leitura comum. Se alguém puser uma leitura comum antes da trava, a busca sem trava passaria a ver dados antigos.
- O formulário de transferência diz se um e-mail tem conta. Uma mensagem genérica não esconderia isso (a transferência passa ou não passa), então o controle é outro: só com login e no máximo 10 operações por minuto por usuário.
