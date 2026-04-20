# Storytelling revisado — Fluxo de mudança no Projeto (Cliente + Admin)

> Objetivo: descrever o fluxo completo da solicitação de alteração dentro do Projeto, com as regras revisadas.

---

## Contexto

Maria (cliente) tem um projeto em andamento.
João (admin) gerencia esse projeto no painel.

Não existe tela separada de `ChangeRequestResource`. Tudo acontece dentro do projeto.

---

## Capítulo 1 — Cliente abre um pedido de alteração

Maria entra no projeto e vê o cabeçalho com **nome do projeto + ID**.

Na seção **Solicitações de alteração**, ela preenche apenas:
- **Descrição**

Ao enviar:
- o pedido nasce com status **Pedido de alteração** (`requested`)
- `impact_price = null`

> Neste fluxo, não existe campo de título. A referência visual do pedido é o próprio projeto (nome + ID) e o conteúdo da descrição.

---

## Capítulo 2 — Segurança e escopo correto

O sistema valida:
1. usuário autenticado
2. role `client`
3. permissão `submit-change-requests`
4. cliente só pode abrir alteração no próprio projeto

Se falhar qualquer regra, retorna 403.

---

## Capítulo 3 — Visão operacional do admin (priorização)

João abre a área de Projetos e já vê uma badge no menu:
- **Projetos [5]**

Esse número representa projetos com solicitações não respondidas.

Na listagem de projetos, a ordenação padrão é pela quantidade de solicitações pendentes de resposta do admin, por exemplo:
1. Projeto X — 2
2. Projeto Y — 2
3. Projeto Z — 1

Ao abrir o projeto, na lista de solicitações cada item mostra:
1. descrição
2. status
3. impacto

> O campo “solicitante” não é necessário na UI, pois o projeto já define o dono.

---

## Capítulo 4 — Admin analisa e envia cotação

João analisa a solicitação e informa o impacto.

Regra obrigatória:
- `impact_price` é obrigatório no envio da análise
- pode ser `0` quando não há cobrança
- antes da análise, permanece `null`

Regra de status:
- o status **não é editado manualmente**
- ao clicar em enviar análise/cotação, o sistema muda automaticamente para **Orçada** (`quoted`)

---

## Capítulo 5 — Cliente recebe notificação e decide

Maria recebe notificação de que a alteração foi analisada.

Ela vê 3 botões:
1. **Aprovar**
2. **Reprovar**
3. **Alterar**

### Se clicar em Aprovar
- status vai para `client_approved`
- botões somem

### Se clicar em Reprovar
- status vai para `rejected`
- botões somem

### Se clicar em Alterar
- status vai para `revision`
- aparece campo para editar a descrição
- ao salvar:
   - status volta para `requested`
   - cotação é resetada (`impact_price = null`)

---

## Capítulo 6 — Pagamento após aprovação do cliente

Quando a solicitação está em `client_approved`, João vê botão para **Gerar pagamento**.

Após gerar:
- sistema salva `payment_link`
- exibe seletor de status de pagamento para o admin atualizar após confirmação

---

## Capítulo 7 — Cliente paga e mudança entra em fila de desenvolvimento

Maria clica no link e paga.

Após confirmação, o pedido muda para:
- **Pendente desenvolvimento** (`pending_development`)

Esse status marca que a alteração foi aprovada e paga, pronta para ser planejada na execução.

---

## Estratégia de prazo (sem duplicar regra pesada)

Para não replicar toda a regra de negócio de etapas do projeto em cada alteração, usar um modelo leve:

- cada solicitação aprovada recebe um **peso de alteração** (`change_weight`)
- ex.: 1 (leve), 2 (média), 3 (alta)
- o total de pesos em `pending_development` ajuda na previsão macro

Ideia prática:
- prazo estimado da fila de alterações = soma dos pesos / capacidade semanal do time

Assim, existe previsibilidade sem criar uma mini-esteira complexa por solicitação.

---

## Linha do tempo resumida

1. Cliente cria descrição → `requested` (`impact_price = null`)
2. Admin informa impacto e envia análise → `quoted`
3. Cliente aprova/reprova/solicita alteração
4. Se aprovar → `client_approved`
5. Admin gera pagamento + acompanha status
6. Pagamento confirmado → `pending_development`

---

## Critérios de aceite desta versão

1. Solicitação do cliente é criada só com descrição.
2. UI usa projeto (nome + ID) como referência principal.
3. Menu/lista de projetos exibe e ordena por pendências de solicitação.
4. `impact_price` é obrigatório no envio da análise do admin (aceita `0`, inicia `null`).
5. Status muda automaticamente para `quoted` no submit da análise.
6. Cliente recebe decisão com botões Aprovar/Reprovar/Alterar.
7. Em Alterar: vai para `revision`, permite edição, volta para `requested` e zera cotação para `null`.
8. Após aprovação, admin gera pagamento e pedido pode evoluir para `pending_development`.
