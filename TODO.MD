# Documentação Técnica: Sistema de Negociação e Gestão de Projetos

Este documento detalha a arquitetura, banco de dados e modelagem de UI para um sistema de freelance/negociação utilizando **Laravel 12/13**, **Filament v5**, **MySQL** e **Spatie Permissions**.

---

## 1. Stack Tecnológica

| Componente            | Tecnologia                       |
| :-------------------- | :------------------------------- |
| **Framework**         | Laravel 12/13                    |
| **Painel Admin/User** | Filament v5                      |
| **Banco de Dados**    | MySQL                            |
| **Permissões**        | Spatie Laravel-Permission        |
| **Pagamentos**        | Mercado Pago (Link de Pagamento) |

---

## 2. Arquitetura de Banco de Dados (MySQL)

As permissões e papéis serão gerenciados pelas tabelas padrão do pacote **Spatie Laravel-Permission** (`roles`, `permissions`, `model_has_roles`, etc.).

### 2.1. Tabela: `users`

Armazena os dados básicos de autenticação.

- `id` (PK)
- `name`
- `email`
- `password`
- `remember_token`
- `timestamps`

### 2.2. Tabela: `products`

Tipos de serviços/produtos oferecidos (ex: Landing Page, E-commerce).

- `id` (PK)
- `name` (string)
- `description` (text)
- `base_price` (decimal)
- `is_active` (boolean)
- `timestamps`

### 2.3. Tabela: `orders` (Pedidos)

O ponto de entrada do cliente.

- `id` (PK)
- `user_id` (FK -> users)
- `product_id` (FK -> products)
- `description` (text) - O que o cliente quer.
- `target_price` (decimal) - Quanto o cliente pretende pagar.
- `status` (enum: pending, negotiating, approved, paid, in_progress, completed, cancelled)
- `timestamps`

### 2.4. Tabela: `proposals` (Negociações)

Registra as idas e vindas da negociação.

- `id` (PK)
- `order_id` (FK -> orders)
- `sender_id` (FK -> users) - Quem enviou a proposta (Cliente ou Admin).
- `content` (text) - Detalhes da proposta/contraproposta.
- `price` (decimal) - Valor proposto.
- `is_accepted` (boolean, nullable)
- `timestamps`

### 2.5. Tabela: `projects`

Criado após a aprovação e pagamento da `order`.

- `id` (PK)
- `order_id` (FK -> orders)
- `payment_link` (string) - Link do Mercado Pago.
- `payment_status` (string) - Status retornado pelo webhook.
- `github_url` (string, nullable)
- `deploy_url` (string, nullable)
- `current_progress` (integer) - Porcentagem calculada (0-100).
- `timestamps`

### 2.6. Tabela: `project_steps` (Etapas)

Definidas pelo Admin antes do início.

- `id` (PK)
- `project_id` (FK -> projects)
- `title` (string) - Ex: "HTML", "CSS", "Integração API".
- `weight` (integer) - Peso da etapa para o cálculo da % (ex: 10, 20).
- `is_completed` (boolean)
- `completed_at` (timestamp, nullable)
- `timestamps`

### 2.7. Tabela: `change_requests` (Solicitações de Mudança)

- `id` (PK)
- `project_id` (FK -> projects)
- `requester_id` (FK -> users)
- `description` (text)
- `status` (enum: pending, approved, rejected)
- `impact_price` (decimal, default 0)
- `timestamps`

---

## 3. Fluxo de Negociação e Pagamento

1. **Início:** Cliente escolhe um `Product`, descreve a necessidade e sugere um `target_price`.
2. **Negociação:**
    - O Admin recebe e cria uma `Proposal` com os detalhes técnicos e o preço real.
    - O Cliente pode aceitar, recusar ou enviar uma nova `Proposal`.
    - Cada interação gera um registro na tabela `proposals`.
3. **Acordo:** Quando uma das partes aceita a proposta da outra, o status da `Order` muda para `approved`.
4. **Pagamento:** O sistema gera um link do Mercado Pago. Ao confirmar o pagamento via Webhook, o status muda para `paid` e o `Project` é instanciado.

---

## 4. Modelagem de UI Centralizada (Filament v5)

Para evitar a fragmentação de ter um Resource para cada tabela, a interface será organizada por **Contexto de Fluxo**.

### 4.1. Dashboard do Cliente (Painel Centralizado)

Em vez de navegar por "Pedidos", "Propostas" e "Projetos" separadamente, o cliente terá um **Project Hub**.

- **Resource: `OrderResource`**
    - **List:** Apenas pedidos ativos.
    - **View/Edit:** Uma página customizada (Custom Page) que funciona como um "Chat de Negociação".
        - No topo: Status atual e resumo do pedido.
        - No meio: Timeline de `proposals` (estilo chat ou histórico).
        - Na base: Botões de ação (Aceitar, Recusar, Nova Contraproposta).
- **Resource: `ProjectResource`**
    - **View:** Dashboard do projeto.
        - Barra de progresso visual (baseada no peso das `project_steps`).
        - Links de acesso (GitHub/Deploy) que só aparecem se o status for `completed`.
        - Widget de "Solicitar Mudança".

### 4.2. Painel do Admin (Gestão de Fluxo)

- **Kanban Board:** Para visualizar as `Orders` em cada estágio (Negociação -> Pagamento -> Desenvolvimento).
- **Project Builder:** Ao aprovar um projeto, o Admin usa um `Repeater` do Filament para definir as `project_steps` e seus pesos antes de iniciar.
- **Change Request Manager:** Interface centralizada para aprovar/rejeitar mudanças, com campo para ajuste de valor que reflete no `Project`.

### 4.3. Lógica de Porcentagem

A porcentagem exibida ao cliente não é manual. É calculada:
`SUM(weight of completed steps) / SUM(total weight) * 100`.
Isso garante que o cliente veja um progresso real baseado nas entregas técnicas que você marcou como concluídas.

### 4.4. Estrutura de Navegação do Filament

Para garantir uma experiência centralizada e evitar a criação excessiva de Resources, a navegação do Filament será estruturada da seguinte forma:

- **Dashboard Principal:** Uma `Custom Page` que serve como o ponto de entrada para o Admin e o Cliente (com visões diferentes).
    - **Admin:** Visão geral de pedidos pendentes, projetos em andamento, solicitações de mudança.
    - **Cliente:** Visão geral dos seus projetos, status e acesso rápido às negociações.

- **Menu Lateral (Admin):**
    - **`Orders` (Pedidos):** Um `Resource` para gerenciar todos os pedidos, com filtros por status (`pending`, `negotiating`, `approved`, etc.). A visualização de um pedido específico levará a uma `Custom Page` de negociação.
    - **`Products` (Produtos/Serviços):** Um `Resource` simples para gerenciar os tipos de serviços oferecidos.
    - **`Users` (Usuários):** Um `Resource` para gerenciar usuários e suas `roles` (via Spatie).
    - **`Projects` (Projetos):** Um `Resource` para gerenciar projetos, com acesso à `Custom Page` de gerenciamento de etapas e mudanças.
    - **`Change Requests` (Solicitações de Mudança):** Um `Resource` ou uma `Custom Page` para gerenciar todas as solicitações de mudança de forma centralizada.

- **Menu Lateral (Cliente):**
    - **`My Projects` (Meus Projetos):** Uma `Custom Page` que lista os projetos do cliente e permite acesso rápido ao `Project Hub` de cada um.

### 4.5. Implementação de Custom Pages para Centralização

O Filament permite a criação de `Custom Pages` que podem ser usadas para construir interfaces mais complexas e personalizadas, combinando dados de múltiplos Models. Isso será crucial para a experiência centralizada:

- **`NegotiationPage` (Página de Negociação):** Uma `Custom Page` que receberá o `order_id` como parâmetro. Esta página exibirá:
    - Detalhes do `Order`.
    - Uma lista de `Proposals` associadas, formatadas como um chat ou timeline.
    - Um formulário para enviar uma nova `Proposal`.
    - Botões de ação para aceitar/recusar a proposta atual.

- **`ProjectHubPage` (Página do Projeto - Cliente):** Uma `Custom Page` que receberá o `project_id` como parâmetro. Esta página exibirá:
    - Detalhes do `Project`.
    - O progresso em porcentagem e uma visualização das `project_steps`.
    - Links para GitHub/Deploy (se disponíveis).
    - Um formulário para criar uma `Change Request`.
    - Histórico de `Change Requests`.

- **`ProjectManagementPage` (Página de Gerenciamento de Projeto - Admin):** Uma `Custom Page` similar ao `ProjectHubPage`, mas com funcionalidades adicionais para o Admin:
    - Gerenciamento das `project_steps` (marcar como concluída, ajustar pesos).
    - Aprovação/Rejeição de `Change Requests`.
    - Edição de links de GitHub/Deploy.

### 4.6. Spatie Laravel-Permission Integration

As permissões serão usadas para controlar o acesso a estas páginas e funcionalidades:

- **Roles:** `admin`, `client`.
- **Permissions:**
    - `view-admin-dashboard`
    - `manage-orders`
    - `manage-products`
    - `manage-users`
    - `manage-projects`
    - `manage-change-requests`
    - `view-client-dashboard`
    - `view-my-projects`
    - `submit-proposals`
    - `accept-proposals`
    - `submit-change-requests`

Isso garante que cada tipo de usuário veja apenas as informações e ações relevantes para sua função, mantendo a interface limpa e focada.
