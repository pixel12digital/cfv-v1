# Auditoria de Acessibilidade — Telas Aluno e Instrutor

**Data:** 2025-02-03  
**Escopo:** Todas as telas de usuários (aluno) e instrutores — pontos onde textos podem ter baixa acessibilidade em modo escuro.

---

## 1. Resumo por Layout

| Layout | CSS Carregado | Dark Mode | Telas |
|--------|---------------|-----------|-------|
| **mobile-first.php** | theme-tokens, mobile-first, theme-overrides | ✅ Sim | aluno/dashboard, dashboard-mobile; instrutor/dashboard-mobile |
| **Página standalone** | Bootstrap + mobile-first.css + CSS próprio | ⚠️ Parcial | aluno/notificacoes, historico, financeiro, presencas-teoricas, aulas, contato; instrutor/dashboard, aulas, perfil, trocar-senha, ocorrencias, notificacoes, contato |

---

## 2. TELAS ALUNO

### 2.1 aluno/dashboard.php
**Layout:** mobile-first.php ✅

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | aula-turma | 451 | `color: #64748b` | Cinza em card — theme-overrides pode corrigir text-muted |
| 2 | Bloco CSS inline | 854-900 | `#f8fafc`, `#1e293b`, `#64748b`, `#dbeafe`, `#1e40af` | Badges/tags com cores fixas — **sem dark** |

### 2.2 aluno/dashboard-mobile.php
**Layout:** mobile-first.php ✅

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Bloco CSS | 395-438 | `#e2e8f0`, `#059669`, `#f1f5f9`, `#94a3b8`, `#1e293b`, `#64748b` | Timeline/progresso — cores fixas em `<style>` — **sem dark** |

### 2.3 aluno/notificacoes.php
**Layout:** Standalone (Bootstrap + aluno-dashboard.css) — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | .stat-item | 120 | `background: white` | Fundo fixo |
| 2 | .notificacao-item | 143-154 | `#e2e8f0`, `#94a3b8`, `#ffffff`, `#3b82f6`, `#f0f7ff` | Cards e bordas — **sem dark** |
| 3 | .empty-state i | 174 | `color: #cbd5e1` | Ícone vazio — em light pode ser fraco |
| 4 | Ícone inline | 256 | `color: #cbd5e1` | Idem |
| 5 | Indicador | 275 | `background: #3b82f6` | Bolinha azul — OK |
| 6 | text-primary, text-warning, text-success | 205, 211, 217 | Bootstrap | Depende de overrides — **não carrega theme-overrides** |

### 2.4 aluno/presencas-teoricas.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Bloco CSS | 230-294 | `#1e293b`, `#2563eb`, `#eff6ff`, `#dcfce7`, `#166534`, `#fef3c7`, `#92400e`, `#fee2e2`, `#991b1b`, `#64748b`, `#cbd5e1` | Muitas cores fixas — **sem dark** |
| 2 | Títulos/parágrafos | 336-337, 376-396, 431-432, 507-508 | `color: #1e293b`, `#64748b`, `#94a3b8` | Texto escuro — ilegível em dark |

### 2.5 aluno/historico.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Bloco CSS | 171 | `color: #1e293b` | Título — **sem dark** |
| 2 | text-primary, text-muted, text-info | 185, 188, 244, 256, 300, 311, 320-322 | Bootstrap | **Não carrega theme-overrides** |

### 2.6 aluno/financeiro.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Bloco CSS | 199 | `color: #64748b` | **Sem dark** |
| 2 | Ícone empty | 322 | `color: #cbd5e1` | Em light pode ser fraco |
| 3 | text-primary, text-muted, text-danger, text-success, text-secondary | 237, 240, 287-310, 324, 348, 352, 356, 362 | Bootstrap | **Não carrega theme-overrides** |

### 2.7 aluno/contato.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Bloco CSS | 248 | `background: #f8fafc` | **Sem dark** |
| 2 | Ícones contato | 330, 345, 359, 373, 386 | `background: #25D366`, `#2563eb`, `#10b981`, `#f59e0b`, `#ef4444` | Cores de marca — geralmente OK |
| 3 | Ícone empty | 488 | `color: #cbd5e1` | **Sem dark** |
| 4 | text-primary, text-muted, text-danger | 284, 287, 334, 349, 363, 377, 390, 428, 437, 456, 465, 490, 507, 511, 524, 537, 540, 543 | Bootstrap | **Não carrega theme-overrides** |

### 2.8 aluno/aulas.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Ícone empty | 909 | `color: #cbd5e1` | **Sem dark** |
| 2 | badge bg-primary bg-opacity-10 text-primary | 801, 860, 863, 866 | Bootstrap | Pode herdar azul — **verificar em dark** |
| 3 | text-primary, text-muted | 577, 580, 782, 788, 791, 805, 855, 910-911, 929, 934 | Bootstrap | **Não carrega theme-overrides** |

### 2.9 aluno/login.php
**Layout:** Próprio (não é área logada)

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Bloco CSS | 90-154 | `#333`, `#666`, `#667eea`, `#fee`, `#c33`, `#efe`, `#3c3` | Formulário — verificar se tem dark |

---

## 3. TELAS INSTRUTOR

### 3.1 instrutor/dashboard.php
**Layout:** Standalone (mobile-first.css, theme-tokens) — **verificar se carrega theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Dropdown perfil | 685-696 | `color: #333`, `#666`, `#e74c3c` | Itens do menu — **sem dark** |
| 2 | Badges aulas | 1065, 1070 | `#3b82f6`, `#10b981`, `#d1fae5`, `#065f46`, `#fef3c7`, `#92400e` | **Sem dark** |
| 3 | Card aluno | 1426, 1438 | `color: #1e293b`, `#94a3b8` | **Sem dark** |
| 4 | Blocos CSS | 2057-2831 | Múltiplas cores (#f8f9fa, #666, #25d366, #0d6efd, #6c757d, #dbeafe, #1e40af, etc.) | Toast WhatsApp, modais, cards — **sem dark** |
| 5 | text-primary, text-muted, text-success, text-warning | 737, 743, 811, 914-955, 971-972, 1023, 1034-1035, 1209-1278, 1375-1378, 1510, 1530 | Bootstrap | Depende de overrides |

### 3.2 instrutor/dashboard-mobile.php
**Layout:** mobile-first.php ✅

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | text-primary (nome aluno) | 353, 506 | Link | theme-overrides corrige |
| 2 | text-muted | Vários | Bootstrap | theme-overrides corrige |
| 3 | spinner | 1024, 1216 | text-primary | theme-overrides corrige |

### 3.3 instrutor/aulas.php
**Layout:** Standalone (mobile-first.css)

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | text-primary (nome aluno) | 345 | Link | **Verificar** — pode não carregar theme-overrides |
| 2 | text-muted | 921 | Bootstrap | Idem |
| 3 | spinner | 598, 845 | text-primary | Idem |

### 3.4 instrutor/perfil.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Alerts | 93, 99 | `#d4edda`, `#155724`, `#f8d7da`, `#721c24` | **Sem dark** |
| 2 | Labels, inputs | 110-229 | `color: #333`, `#e74c3c`, `#666`, `#2563eb`, `#f0f0f0`, `#ddd` | **Sem dark** |
| 3 | alertDiv (JS) | 329, 380 | Cores inline | **Sem dark** |

### 3.5 instrutor/trocar-senha.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Alerts | 158, 164, 171 | `#d4edda`, `#155724`, `#f8d7da`, `#721c24`, `#fff3cd`, `#856404` | **Sem dark** |
| 2 | Labels, inputs | 185-256 | `color: #333`, `#e74c3c`, `#666`, `#2563eb`, `#f0f0f0`, `#333` | **Sem dark** |

### 3.6 instrutor/ocorrencias.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Alerts | 269, 275 | `#d4edda`, `#155724`, `#f8d7da`, `#721c24` | **Sem dark** |
| 2 | Formulário | 283-366 | `color: #1e293b`, `#333`, `#e74c3c`, `#666`, `#2563eb` | **Sem dark** |
| 3 | Empty state | 381-433 | `#cbd5e1`, `#64748b`, `#94a3b8`, `#e2e8f0`, `#f8fafc`, `#3b82f6`, `#f0fdf4`, `#10b981`, `#059669`, `#065f46` | **Sem dark** |
| 4 | alertDiv (JS) | 465, 486 | Cores inline | **Sem dark** |

### 3.7 instrutor/notificacoes.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | stat-value | 140 | `color: #2563eb` | Azul — **sem dark** |
| 2 | Demais elementos | — | Bootstrap + CSS próprio | **Verificar** |

### 3.8 instrutor/contato.php
**Layout:** Standalone — **NÃO usa theme-overrides**

| # | Elemento | Linha | Estilo | Risco Dark |
|---|----------|-------|--------|------------|
| 1 | Diversos | — | Bootstrap + estilos próprios | **Verificar** |

---

## 4. Inventário de Telas

### Aluno (11 arquivos)

| Arquivo | Layout | theme-overrides | Pontos Críticos |
|---------|--------|-----------------|-----------------|
| dashboard.php | mobile-first | ✅ | CSS inline com #dbeafe, #1e40af |
| dashboard-mobile.php | mobile-first | ✅ | CSS inline timeline (#e2e8f0, #059669, etc.) |
| notificacoes.php | standalone | ❌ | Cards white, #3b82f6, #cbd5e1 |
| presencas-teoricas.php | standalone | ❌ | #1e293b, #64748b em textos — **alto risco** |
| historico.php | standalone | ❌ | #1e293b, text-* sem override |
| financeiro.php | standalone | ❌ | #64748b, #cbd5e1, text-* |
| contato.php | standalone | ❌ | #f8fafc, #cbd5e1, text-* |
| aulas.php | standalone | ❌ | #cbd5e1, badge text-primary |
| login.php | próprio | — | #333, #666 (formulário) |
| app.php | redirect | — | — |
| logout.php | redirect | — | — |

### Instrutor (14 arquivos)

| Arquivo | Layout | theme-overrides | Pontos Críticos |
|---------|--------|-----------------|-----------------|
| dashboard.php | standalone | ⚠️ | Muitos #333, #666, #dbeafe, #1e40af, dropdown |
| dashboard-mobile.php | mobile-first | ✅ | OK |
| aulas.php | standalone | ⚠️ | text-primary, text-muted |
| perfil.php | standalone | ❌ | #333, #666, alerts, labels |
| trocar-senha.php | standalone | ❌ | Alerts, labels, inputs |
| ocorrencias.php | standalone | ❌ | Form, empty state, #1e293b |
| notificacoes.php | standalone | ❌ | #2563eb |
| contato.php | standalone | ❌ | Verificar |
| app.php | redirect | — | — |
| api/perfil.php | API | — | — |
| debug_* | debug | — | — |
| test-* | teste | — | — |

---

## 5. Padrões de Risco Identificados

### Alto risco (texto ilegível em dark)
- `color: #1e293b` em fundo escuro
- `color: #333`, `#666` em fundo escuro
- `color: #64748b`, `#94a3b8` em fundo escuro (depende do contraste)
- `#dbeafe` + `#1e40af` (badge info) — texto azul escuro
- Páginas que **não carregam** theme-overrides.css

### Médio risco
- `background: white` fixo em cards
- `text-primary`, `text-muted` sem override (Bootstrap padrão)
- Alerts com cores fixas (#d4edda, #f8d7da, etc.)

### Baixo risco
- Cores de marca em ícones (#25D366, #2563eb)
- Elementos com theme-overrides aplicado

---

## 6. Ações Recomendadas (por prioridade)

### Prioridade 1 — Telas standalone sem theme-overrides
Incluir `theme-tokens.css` e `theme-overrides.css` em:
- aluno/notificacoes.php
- aluno/presencas-teoricas.php
- aluno/historico.php
- aluno/financeiro.php
- aluno/contato.php
- aluno/aulas.php
- instrutor/perfil.php
- instrutor/trocar-senha.php
- instrutor/ocorrencias.php
- instrutor/notificacoes.php
- instrutor/contato.php
- instrutor/aulas.php
- instrutor/dashboard.php

### Prioridade 2 — Substituir cores hardcoded
- `#1e293b` → `var(--theme-text)`
- `#64748b`, `#94a3b8` → `var(--theme-text-muted)` ou `var(--theme-text-secondary)`
- `#dbeafe`, `#1e40af` → `var(--theme-info-bg)`, `var(--theme-info-text)`
- `background: white` → `var(--theme-card-bg)` ou `var(--theme-surface)`

### Prioridade 3 — Blocos `<style>` com dark mode
Adicionar `@media (prefers-color-scheme: dark)` em todos os blocos CSS inline que definem cores.

---

**Fim do inventário.**
