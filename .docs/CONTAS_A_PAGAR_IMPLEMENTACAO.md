# Contas a Pagar – Implementação

## Visão geral

Módulo **Contas a Pagar** (agenda de pagamentos): cadastro de lançamentos, status (aberto / pago / vencido), baixa de pagamento, estorno e relatório por período. Sem conciliação bancária nem integrações; apenas registro e consulta, reutilizando o financeiro existente.

**Tabela:** `financeiro_pagamentos` (já existente – migration 007).  
**API:** `admin/api/financeiro-despesas.php`.  
**Página:** `admin/index.php?page=financeiro-despesas`.

---

## Regras de negócio

- **Criar:** descrição (obrigatório), valor (obrigatório), vencimento (obrigatório). Categoria e observações opcionais. Fornecedor opcional (usa descrição se vazio).
- **Status exibição:**
  - **Em aberto:** `status = pendente` e vencimento ≥ hoje.
  - **Vencida:** `status = pendente` e vencimento &lt; hoje (calculado, não gravado).
  - **Paga:** `status = pago`.
- **Baixar pagamento:** marcar como paga e registrar data de pagamento (valor mantido).
- **Editar:** permitido só para conta em aberto ou vencida. Conta paga **não** pode ser editada; só **estornar** (volta para pendente e zera data de pagamento).
- **Excluir:** apenas contas em aberto (pendentes).

---

## Permissões (RBAC)

- **Acesso:** apenas **SECRETARIA** e **ADMIN**.
- Bloqueio no roteamento: em `admin/index.php`, se `page=financeiro-despesas` e o usuário não for admin nem secretaria, redireciona com mensagem de permissão.
- API retorna 403 se o usuário não for admin ou secretaria.

---

## Endpoints da API

- **GET** `?id=...` – uma conta.
- **GET** (lista) – parâmetros: `status` (aberto|pago|vencido), `data_inicio`, `data_fim`, `categoria`, `page`, `limit`. Retorna `data[]` com `status_exibicao` em cada item.
- **GET** `?relatorio=totais&data_inicio=...&data_fim=...` – totais do período (aberto, vencido, pago: valor e quantidade).
- **GET** `?export=csv&...` – exporta CSV com os mesmos filtros.
- **POST** – criar conta (body JSON: `descricao`, `valor`, `vencimento`; opcional: `categoria`, `observacoes`, `fornecedor`).
- **PUT** `?id=...` – editar (só em aberto), ou body `action: "baixar"` / `action: "estornar"`.

---

## Relatório por período

Na própria página **Contas a Pagar**:

- Bloco **Resumo do período** com os mesmos filtros de data (vencimento de/até).
- Totais: **Em aberto**, **Vencidas** e **Pagas** (valor e quantidade), batendo com a listagem filtrada.

---

## Arquivos alterados/criados

| Arquivo | Alteração |
|--------|-----------|
| `admin/api/financeiro-despesas.php` | Reescrita: filtros período/status, status_exibicao, POST com descricao/valor/vencimento, PUT baixar/estornar, relatório totais, CSV, require `../../includes`. |
| `admin/pages/financeiro-despesas.php` | Reescrita: tabela `financeiro_pagamentos`, status aberto/vencido/pago, filtros, modal nova conta, editar, baixar, estornar, resumo do período, link Exportar CSV. |
| `admin/index.php` | Bloqueio de acesso a `financeiro-despesas` para quem não é admin nem secretaria. |
| `admin/pages/financeiro-relatorios.php` | Uso de `financeiro_pagamentos` (status `pago`) no lugar de `financeiro_despesas`. |

A API antiga `admin/api/despesas.php` (tabela `despesas`) permanece no projeto; o módulo Contas a Pagar usa apenas `financeiro_pagamentos` e `admin/api/financeiro-despesas.php`.
