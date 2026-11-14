# 0003 · Livro-razão e estorno

> Quem estorna e onde as regras são garantidas mudou no [ADR 0005](0005-regras-no-banco-estorno-e-extrato.md).

## Contexto

O desafio pede que depósito e transferência possam ser revertidos, inclusive por inconsistência. Também pede que o depósito some ao saldo mesmo quando ele está negativo, o que só acontece se algo tirar dinheiro de quem já gastou.

## Decisão

Cada movimento gera um **lançamento** em `lancamentos`: valor positivo é crédito, negativo é débito, com o saldo logo depois. Lançamento nunca é alterado nem apagado; o usuário `app` do banco nem tem permissão para isso. O saldo da carteira é uma cópia para leitura rápida e tem que bater com a soma dos lançamentos.

Só a `Operacao` mexe no saldo (`creditar`, `debitar`, `debitarMesmoSemSaldo`): ela altera a carteira e grava o lançamento juntos. As actions não gravam lançamento direto.

Reverter é **estornar**: criar uma operação nova, espelhada, que aponta para a original (`estorna_id`). A original passa a `estornada` e os lançamentos dela continuam lá. O estorno não confere saldo, então pode deixar a carteira negativa; é o "saldo negativo por algum motivo" do enunciado.

Não usei soft delete. Ele precisaria de `UPDATE` em `lancamentos`, esconderia registros das consultas sem avisar (a soma deixaria de bater) e apagar não é o que se faz com dinheiro: o erro e a correção ficam visíveis.

Quem estorna: depósito, quem depositou; transferência, quem enviou ou quem recebeu; qualquer operação, o suporte pelo `carteira:estornar`, com motivo e nome. O `carteira:conciliar` só detecta divergência; corrigir é decisão de uma pessoa.

## Consequências

- Dá para auditar qualquer saldo e reconstruí-lo a partir dos lançamentos.
- Estorno pedido por quem enviou é direto aqui, porque o enunciado pede "a pedido do usuário". Num produto real, quem recebeu poderia ser prejudicado depois de entregar algo; o pedido passaria por aprovação ou teria prazo curto.
- O extrato cresce para sempre. Com volume grande, entraria arquivamento por período, nunca exclusão.
