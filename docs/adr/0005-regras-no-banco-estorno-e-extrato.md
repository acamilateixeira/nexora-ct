# 0005 · Regras no banco, estorno por solicitação e extrato por período

## Contexto

Três problemas apareceram testando o sistema como atacante e com volume. Um `UPDATE` direto no saldo passava, e só a conciliação percebia depois. Quem enviava uma transferência estornava sozinho e deixava quem recebeu negativo. E o extrato, com 1 milhão de lançamentos, levava até 2 s nas páginas finais, porque fazia `COUNT(*)` e `OFFSET` a cada página.

## Decisão

**Regras no banco.** Triggers no MySQL valem para qualquer usuário, não só para a aplicação. Lançamento e operação não são alterados nem apagados. O lançamento precisa ser de uma carteira da operação, com o sinal e o valor dela, e o `saldo_apos` precisa continuar o saldo anterior. O saldo da carteira só muda pelo trigger do lançamento. Operação só vai de `concluida` para `estornada`, e só com o estorno gravado. Transferência só sai com saldo disponível (`saldo - bloqueado`); estorno acontece sempre, mesmo deixando a carteira negativa. A aplicação valida antes e dá mensagem amigável; o trigger é a última barreira.

A marca `@lancamento_na_carteira` é uma variável de sessão, e qualquer sessão consegue defini-la. Por isso ela não é a proteção: o usuário `app` não tem `UPDATE` na coluna `saldo`, só em `bloqueado` e `updated_at`. O trigger do lançamento grava o saldo com as permissões do `migrator`, que o criou. Quem tem acesso de administrador ainda consegue contornar (`SET @lancamento_na_carteira`, `DROP TRIGGER`); para isso continua a conciliação.

**Estorno.** Depósito vem de fora da carteira, então só o atendimento estorna (`make estornar`), com o nome gravado, mesmo deixando o saldo negativo. Na transferência, como no Pix: quem recebeu pode devolver, e a devolução acontece mesmo sem saldo, porque exigir saldo deixaria o prejuízo com quem pagou. Quem enviou contesta (`solicitacoes_estorno`, uma por transferência), e quem analisa é o atendimento, não o recebedor. Durante a análise, o valor fica bloqueado na carteira de quem recebeu, até o quanto houver disponível (bloqueio cautelar, como no MED); o saldo disponível é `saldo - bloqueado`. Aprovar é o `carteira:estornar`, que fecha a contestação na mesma transação; negar é o `carteira:negar-estorno`. A negação trava a operação antes da contestação, na mesma ordem do estorno, então as duas ao mesmo tempo não dão deadlock.

**Extrato.** Período de 7, 30 ou 90 dias, ou datas com no máximo 90 dias; filtro de entradas e saídas; totais do período no lugar do contador geral; paginação por cursor em `(created_at, id)`. O índice `(carteira_id, created_at, id, valor)` cobre a soma e o filtro sem ler a tabela. O cursor vem da URL e é conferido antes de usar, porque o Laravel confere o formato do cursor, mas não o tipo de cada campo.

Medido com 1 milhão de lançamentos numa carteira: 30 dias em ~45 ms, 90 dias em ~55 ms, o pior filtro em ~90 ms, e a última página custando o mesmo que a primeira. Antes: 120 ms na primeira página e até 2,2 s na última.

## Consequências

- O saldo deixa de ser gravado pela aplicação; o model só se sincroniza depois do lançamento.
- O MySQL precisa de `log_bin_trust_function_creators` para o `migrator` criar triggers (no compose e no CI).
- Negada a contestação, não há segundo pedido pela tela.
