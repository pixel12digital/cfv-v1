# Auditoria: Textos em Azul e Acessibilidade no Modo Escuro (Mobile)

**Data:** 2025-02-03  
**Objetivo:** Identificar todos os pontos onde textos em azul causam dificuldade de visualização quando o celular está em modo escuro.  
**Escopo:** APP mobile (instrutor e aluno) — sem implementações, apenas varredura criteriosa.

---

## 1. Resumo Executivo

Foram identificados **dois ecossistemas distintos** de layout no sistema:

| Ecossistema | Layout | CSS Carregado | Suporte Dark Mode |
|-------------|--------|---------------|-------------------|
| **App (agenda, dashboard app)** | `app/Views/layouts/shell.php` | tokens.css, layout.css, components.css, utilities.css | **Parcial** — `tokens.css` não sobrescreve `--color-primary` em dark |
| **PWA Mobile (instrutor/aluno)** | `includes/layout/mobile-first.php` | theme-tokens.css, mobile-first.css, theme-overrides.css | **Completo** — theme-overrides aplica correções |

A tela "Detalhes da Aula" da imagem pertence ao **App (shell.php)**. O `tokens.css` define `--color-primary: #023A8D` (azul escuro) e **não possui override para dark mode**, resultando em texto azul escuro sobre fundo escuro = baixo contraste.

---

## 2. Pontos de Atenção — Tela "Detalhes da Aula" (agenda/show.php)

| # | Elemento | Arquivo | Linha | Estilo Atual | Problema em Dark Mode |
|---|----------|---------|-------|--------------|------------------------|
| 1 | **Tag "2 aulas consecutivas"** | `app/Views/agenda/show.php` | 174-175 | `background: #dbeafe; color: #1e40af` (hardcoded) | Texto azul escuro (#1e40af); em fundo escuro ou quando o badge herda fundo do card, contraste insuficiente |
| 2 | **Nome do aluno (link)** | `app/Views/agenda/show.php` | 188 | `color: var(--color-primary);` | `--color-primary` = #023A8D (tokens.css) — azul escuro em fundo escuro |
| 3 | **KM Inicial** | `app/Views/agenda/show.php` | 266 | `color: var(--color-primary, #3b82f6);` | Mesmo que acima — azul escuro em fundo escuro |
| 4 | **Link Matrícula** (admin) | `app/Views/agenda/show.php` | 205 | `color: var(--color-primary);` | Idem |
| 5 | **Tag localização teórica** | `app/Views/agenda/show.php` | 108 | `background: #dbeafe; color: #1e40af` (hardcoded) | Mesmo padrão do item 1 |
| 6 | **Badge .badge-info** | `app/Views/agenda/show.php` | 571-572 | `background: #dbeafe; color: #1e40af` | Cores fixas — sem adaptação dark |

---

## 3. Pontos de Atenção — App Instrutor (shell + mobile-first)

### 3.1 App shell (agenda, dashboard app)

| # | Elemento | Arquivo | Linha | Estilo | Problema |
|---|----------|---------|-------|--------|----------|
| 7 | Abas (Próximas/Histórico/Todas) | `app/Views/agenda/index.php` | 161, 166, 171 | `color: var(--color-primary, #3b82f6)` | Azul em dark |
| 8 | Status "Agendada" | `app/Views/agenda/index.php` | 196 | `color: #3b82f6; bg: #dbeafe` | Hardcoded azul |
| 9 | Link "Ver Detalhes" (JS) | `app/Views/agenda/index.php` | 1181 | `background: #3b82f6; color: white` | Botão azul — OK se fundo branco; em dark o botão pode ter contraste reduzido dependendo do contexto |
| 10 | Nome aluno, "aulas consecutivas" | `app/Views/dashboard/instrutor.php` | 80, 166, 177 | `#dbeafe`, `#1e40af`, `color: #1e40af` | Hardcoded azul |
| 11 | KM Inicial | `app/Views/agenda/show.php` | 266 | Já listado acima | — |
| 12 | Links alunos/matrículas | `app/Views/agenda/show.php` | 188, 205 | Já listados | — |

### 3.2 PWA Mobile (instrutor/dashboard-mobile.php, instrutor/aulas.php)

| # | Elemento | Arquivo | Linha | Estilo | Problema |
|---|----------|---------|-------|--------|----------|
| 13 | **Nome do aluno (link)** | `instrutor/dashboard-mobile.php` | 353, 506 | `class="text-primary"` | Bootstrap `text-primary` → theme-overrides força `var(--theme-link, #60a5fa)` em dark — **corrigido** |
| 14 | Nome do aluno | `instrutor/aulas.php` | 345 | `class="text-primary"` | Idem — **corrigido** |
| 15 | KM inicial (texto) | `instrutor/dashboard-mobile.php` | 387, 540 | `class="text-muted"` | theme-overrides força #cbd5e1 em dark — **corrigido** |
| 16 | Botões `btn-outline-primary`, `btn-outline-info` | Vários | — | Classes Bootstrap | theme-overrides aplica borda/cor adequadas em dark — **corrigido** |

**Conclusão PWA Instrutor:** Os elementos que usam `text-primary` e `text-muted` já são tratados por `theme-overrides.css` em dark mode. O risco está em **telas do App (shell)** que não carregam theme-overrides.

---

## 4. Pontos de Atenção — App Aluno

### 4.1 App shell (dashboard aluno)

| # | Elemento | Arquivo | Linha | Estilo | Problema |
|---|----------|---------|-------|--------|----------|
| 17 | Badge "X aulas" | `app/Views/dashboard/aluno.php` | 76 | `background: var(--color-primary, #3b82f6)` | `--color-primary` sem override dark |
| 18 | Status "Agendada" | `app/Views/dashboard/aluno.php` | 223 | `bg: #dbeafe; border: #3b82f6` | Hardcoded azul |
| 19 | Barra de progresso teórico | `app/Views/dashboard/aluno.php` | 290 | `background: var(--color-primary)` | Idem |

### 4.2 PWA Mobile (aluno/dashboard-mobile.php, aluno/aulas.php)

| # | Elemento | Arquivo | Linha | Estilo | Problema |
|---|----------|---------|-------|--------|----------|
| 20 | Badge notificações | `aluno/dashboard-mobile.php` | 114 | `class="badge bg-primary"` | Bootstrap — depende do tema |
| 21 | Botão "Reagendar" | `aluno/dashboard-mobile.php` | 244 | `class="btn btn-outline-primary"` | theme-overrides — **corrigido** |
| 22 | Badge "Turma" | `aluno/aulas.php` | 801, 863 | `bg-primary bg-opacity-10 text-primary` | Pode herdar azul — verificar em dark |
| 23 | Ícones text-primary | `aluno/aulas.php`, `aluno/financeiro.php`, etc. | Vários | `class="text-primary"` | theme-overrides — **corrigido** |

---

## 5. Variáveis CSS e Dark Mode

### 5.1 tokens.css (App shell)

```css
:root {
  --color-primary: #023A8D;  /* Azul escuro */
  /* ... */
}

@media (prefers-color-scheme: dark) {
  :root {
    --color-text: #f8f9fa;
    --color-text-muted: #adb5bd;
    --color-bg: #212529;
    --color-bg-light: #343a40;
    --color-border: #495057;
    /* --color-primary NÃO É SOBRESCRITO — permanece #023A8D */
  }
}
```

**Problema:** `--color-primary` continua #023A8D em dark mode. Texto azul escuro sobre fundo #212529 ou #343a40 = contraste insuficiente (WCAG AA).

### 5.2 theme-tokens.css (PWA mobile)

```css
@media (prefers-color-scheme: dark) {
  :root {
    --theme-link: #60a5fa;      /* Azul claro legível */
    --theme-primary: #34d399;   /* Verde para primary */
    /* ... */
  }
}
```

**Conclusão:** O PWA mobile usa `--theme-link` e `--theme-primary` adaptados. O App shell usa `--color-primary` sem adaptação.

---

## 6. Cores Hardcoded em Azul (sem variáveis)

| Cor | Uso | Arquivos Afetados |
|-----|-----|-------------------|
| `#dbeafe` | Background de badges/tags info | agenda/show.php, agenda/index.php, dashboard/instrutor.php, dashboard/aluno.php |
| `#1e40af` | Texto de badges/tags info | agenda/show.php, dashboard/instrutor.php |
| `#3b82f6` | Status agendada, links, botões | agenda/index.php, dashboard/instrutor.php, dashboard/aluno.php |
| `#e0e7ff` / `#3730a3` | Badge teórica | agenda/index.php |
| `#023A8D` | Via --color-primary (tokens.css) | Todo o App shell |

---

## 7. Arquivos que NÃO carregam theme-overrides.css

O `theme-overrides.css` contém as correções de contraste para dark mode (`.text-primary`, `.text-muted`, links, etc.). Ele é carregado apenas por:

- `includes/layout/mobile-first.php` (instrutor/dashboard-mobile, aluno/dashboard-mobile, etc.)

O **App (shell.php)** carrega:

- tokens.css
- components.css
- layout.css
- utilities.css

**Não carrega:** theme-tokens.css, theme-overrides.css, mobile-first.css

Portanto, **qualquer tela renderizada via shell.php** (agenda, dashboard app, iniciar aula, concluir aula, etc.) **não recebe** as correções de dark mode do theme-overrides.

---

## 8. Rotas e Telas Afetadas (App shell)

| Rota | View | Acessível por |
|------|------|---------------|
| `GET /agenda/{id}` | agenda/show.php | Instrutor, Aluno, Admin |
| `GET /agenda` | agenda/index.php | Instrutor, Aluno, Admin |
| `GET /agenda/{id}/iniciar` | agenda/iniciar.php | Instrutor |
| `GET /agenda/{id}/concluir` | agenda/concluir.php | Instrutor |
| `GET /dashboard` | dashboard/instrutor.php ou dashboard/aluno.php | Instrutor, Aluno |

Todas essas telas usam shell.php e sofrem do problema de `--color-primary` e cores hardcoded em azul.

---

## 9. Checklist de Pontos Críticos (Acessibilidade)

| Prioridade | Item | Localização | Ação Sugerida |
|------------|------|-------------|---------------|
| **Alta** | Nome do aluno (link) em Detalhes da Aula | agenda/show.php:188 | Usar variável adaptada a dark ou incluir theme-overrides no shell |
| **Alta** | KM Inicial em Detalhes da Aula | agenda/show.php:266 | Idem |
| **Alta** | Tag "2 aulas consecutivas" | agenda/show.php:174 | Substituir #dbeafe/#1e40af por variáveis de tema |
| **Alta** | --color-primary sem override dark | assets/css/tokens.css | Adicionar `--color-primary` no bloco `@media (prefers-color-scheme: dark)` |
| **Média** | Badges .badge-info | agenda/show.php:108, 571 | Usar variáveis ou classes com suporte dark |
| **Média** | Abas da agenda | agenda/index.php:161-171 | Garantir que usem variável com override |
| **Média** | Status "Agendada" em listas | agenda/index.php, dashboard/*.php | Idem |
| **Baixa** | Botões/links no PWA mobile | dashboard-mobile, aulas.php | Já corrigidos por theme-overrides |

---

## 10. Referências de Código

### agenda/show.php — Trechos críticos

```php
// Linha 174-175 — Tag "aulas consecutivas"
<span style="margin-left: var(--spacing-xs); background: #dbeafe; color: #1e40af; ...">

// Linha 188 — Nome do aluno
<a href="..." style="color: var(--color-primary); text-decoration: none;">

// Linha 266 — KM Inicial
<div style="font-size: 1.25rem; font-weight: 600; color: var(--color-primary, #3b82f6);">
```

### tokens.css — Falta de override

```css
@media (prefers-color-scheme: dark) {
  :root {
    /* --color-primary NÃO está aqui */
  }
}
```

---

## 11. Conclusão

A dificuldade de visualização de textos em azul no modo escuro ocorre principalmente em:

1. **Telas do App (shell.php)** — agenda/show, agenda/index, dashboard, iniciar, concluir
2. **Variável `--color-primary`** em `tokens.css` — não possui valor para dark mode
3. **Cores hardcoded** — `#dbeafe`, `#1e40af`, `#3b82f6` em badges e tags

O **PWA mobile** (dashboard-mobile, aulas.php) que usa `mobile-first.php` já possui correções via `theme-overrides.css`. O problema central está no **App shell**, que não carrega essas correções e depende de `tokens.css` sem override de `--color-primary` para dark mode.
