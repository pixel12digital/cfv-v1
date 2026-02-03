# Auditoria Completa: Pontos de Atenção — Acessibilidade e Visibilidade (Light/Dark, iOS/Android)

**Data:** 2025-02-03  
**Objetivo:** Relação exaustiva de todos os pontos que podem causar dificuldade de leitura ou baixa visibilidade em telas claras/escuras e em iOS/Android.  
**Escopo:** Todo o sistema — sem implementações, apenas inventário criterioso.

---

## 1. Popups e Modais de Instalação PWA (iOS — Caso Reportado)

### 1.1 Modal de instruções iOS — login.php

| Item | Arquivo | Linhas | Problema |
|------|---------|--------|----------|
| **Instruções "Instalar App"** | `login.php` | 1317-1328, 1416-1468 | Estilos injetados via `addIOSStyles()` — **sem suporte dark mode**. Fundo `#f0f9ff`, texto `#2c3e50`, `#7f8c8d`. Em modo escuro do iOS, o usuário não consegue ler. |
| **Classe** | `.pwa-ios-instructions` | 1417-1424 | `background: #f0f9ff`, `border: #1A365D` — fixos |
| **Texto** | `.pwa-ios-text p` | 1448-1453 | `color: #7f8c8d` — cinza em fundo claro; em dark vira ilegível se fundo herdar escuro |
| **Botão fechar** | `.pwa-ios-close` | 1455-1467 | `color: #7f8c8d` — pode sumir em dark |

### 1.2 Modal iOS — app.js (shell)

| Item | Arquivo | Linhas | Problema |
|------|---------|--------|----------|
| **Modal "Instalar Aplicativo"** | `assets/js/app.js` | 354-447 | CSS injetado inline — **sem `@media (prefers-color-scheme: dark)`**. |
| **Conteúdo** | `.ios-install-modal-content` | 397-405 | `background: white` — fixo |
| **Título** | `.ios-install-modal-header h3` | 414-416 | `color: #023A8D` — azul escuro; em fundo escuro ilegível |
| **Corpo** | `.ios-install-modal-body` | 424-433 | Sem cor de texto definida — herda; em dark pode herdar escuro sobre escuro |
| **Botão** | `.ios-install-modal-btn` | 439-445 | `background: #023A8D`, `color: white` — botão azul escuro; em dark o azul pode ter baixo contraste com bordas/fundo |
| **Botão fechar** | `.ios-install-modal-close` | 417-423 | `color: #666` — cinza; em dark pode sumir |

### 1.3 Modal iOS — pwa/install-footer.js + install-footer.css

| Item | Arquivo | Linhas | Problema |
|------|---------|--------|----------|
| **Modal "Como instalar no iPhone"** | `pwa/install-footer.css` | 286-408 | `.pwa-ios-modal-content` com `background: white` — **sem override dark**. |
| **Header** | `.pwa-ios-modal-header h4` | 318-323 | `color: #2c3e50` — escuro |
| **Steps** | `.pwa-ios-step-content p` | 376-381 | `color: #2c3e50` |
| **Note** | `.pwa-ios-note` | 387-408 | `background: #e8f4f8`, `color: #2c3e50` — fixos |
| **Responsivo** | `install-footer.css` | 502+ | `@media (max-width: 768px)` — **não há `prefers-color-scheme: dark`** |

### 1.4 Modal de instruções — pwa-install-banner.js

| Item | Arquivo | Linhas | Problema |
|------|---------|--------|----------|
| **Modal "Como instalar"** | `assets/js/pwa-install-banner.js` | 320-394 | HTML com `color: #333` em todos os `<p>`, `color: #1a365d` no título — **inline hardcoded**. |
| **Título** | linha 381 | `color: #1a365d` | Azul escuro — ilegível em dark |
| **Parágrafos** | linhas 337, 346, 349, 356, 359, 366, 369 | `color: #333` | Cinza escuro — ilegível em dark |
| **Botão fechar** | linha 380 | `color: #999` | Cinza — pode sumir em dark |
| **CSS do modal** | `pwa-install-banner.css` | 248-286 | `.pwa-install-modal__content` tem `background: white`; **há** `@media (prefers-color-scheme: dark)` (linhas 369-391) que define fundo `#1e293b` e texto branco — **mas o HTML injetado sobrescreve com inline `color: #333`** (especificidade vence). |

---

## 2. Banners e Overlays de Instalação

### 2.1 PWA Install Banner (dashboard)

| Item | Arquivo | Problema |
|------|---------|----------|
| **Banner principal** | `pwa-install-banner.css` | `background: linear-gradient(#1a365d, #2563eb)` — escuro; texto branco. Em light mode OK. Em dark, se a página já é escura, pode confundir. |
| **Botão primary** | `pwa-install-banner.css` | `background: white`, `color: #1a365d` — dark override existe (384-389) |
| **Toast de sucesso** | `pwa-install-banner.css` | `background: #059669`, `color: white` — OK em ambos |
| **Modal de instruções** | `pwa-install-banner.css` | Tem dark mode (369-391), mas **HTML injetado com inline styles** anula (ver 1.4) |

### 2.2 PWA Install Overlay (dashboard aluno)

| Item | Arquivo | Problema |
|------|---------|----------|
| **Overlay** | `pwa-install-overlay.css` | **Tem** `@media (prefers-color-scheme: dark)` (37-46) — `.pwa-overlay-dialog` com `background: #1e293b`, `color: #e2e8f0` — **correto** |
| **Título h2** | `pwa-install-overlay.css` | Light: `color: #0f172a`; dark: `color: #f1f5f9` — **correto** |

### 2.3 PWA Install Footer (login)

| Item | Arquivo | Problema |
|------|---------|----------|
| **Footer** | `pwa/install-footer.css` | Usa `rgba(255,255,255,...)` — pensado para fundo escuro do login. Se login estiver em dark, pode estar OK. Se houver variação de tema, verificar. |
| **Modal iOS** | `pwa/install-footer.css` | Sem dark mode (ver 1.3) |

### 2.4 PWA Update Banner (pwa-register.js)

| Item | Arquivo | Problema |
|------|---------|----------|
| **Banner de atualização** | `pwa/pwa-register.js` | Usa `var(--theme-card-bg)`, `var(--theme-text)` — **tem** `@media (prefers-color-scheme: dark)` (458-474) — **correto** |
| **Banner de instalação** | `pwa/pwa-register.js` | `.pwa-banner` com `background: #2c3e50`, `color: white` — fixo; em light mode pode contrastar mal com fundo claro. Sem dark override explícito. |

---

## 3. Dialogs Nativos (alert, confirm, prompt)

| Item | Onde | Problema |
|------|------|----------|
| **alert()** | login.php, agenda/index.php, matricula_show.php, agendamento.php, turmas-teoricas, etc. | Dialogs nativos do browser — **não controlamos estilo**. Em iOS Safari dark, o dialog pode herdar tema do sistema; em alguns casos texto fica ilegível. |
| **confirm()** | Vários (matricula_show, agendamento, turmas-teoricas, etc.) | Idem |
| **prompt()** | matricula_show.php (exclusão), turmas-teoricas (debug) | Idem |

**Recomendação:** Substituir por modais customizados com suporte a tema (quando possível) para controle total.

---

## 4. Modais e Popups Genéricos

### 4.1 Modal de cluster (agenda)

| Item | Arquivo | Linhas | Problema |
|------|---------|--------|----------|
| **clusterModal** | `app/Views/agenda/index.php` | 1094-1205, 1148-1181 | HTML gerado via JS com `background: #f9fafb`, `color: #111`, `color: #666`, `background: #3b82f6` — **hardcoded**. Sem dark mode. |
| **Botão fechar** | linha 723 | `color: #666` | Pode sumir em dark |
| **Ver Detalhes** | linha 1181 | `background: #3b82f6` | OK em light; em dark o botão pode ter contraste reduzido dependendo do fundo do modal |

### 4.2 Modais do Admin (popup-modal, modal-root)

| Item | Arquivo | Problema |
|------|---------|----------|
| **#modal-root** | `admin/index.php` | Estilos inline (764-914) — `background: #fff`, cores fixas. **Sem `prefers-color-scheme: dark`**. |
| **.popup-modal** | `admin/index.php` | Idem |
| **.popup-search-input** | `admin/index.php` | Cores fixas |
| **.popup-item-card** | `admin/index.php` | Cores fixas (#6c757d, #dc3545, etc.) |
| **Modal de veículos/salas** | `admin/pages/` | Herdam layout admin — provavelmente sem dark |

### 4.3 Modais de aulas (instrutor/aluno)

| Item | Arquivo | Problema |
|------|---------|----------|
| **Modal de cancelamento** | `app/Views/agenda/show.php` | Usa classes `.card`, `.btn` — depende do layout carregado |
| **Modal de reagendamento** | `app/Views/agenda/show.php` | Idem |
| **Modal de aluno** | `instrutor/dashboard-mobile.php` | Bootstrap modal — depende de theme-overrides |
| **Modal de reagendamento** | `aluno/dashboard-mobile.php`, `aluno/dashboard.php` | Idem |

---

## 5. Toasts e Notificações

| Item | Arquivo | Problema |
|------|---------|----------|
| **PWA install toast** | `pwa-install-banner.css` | `background: #059669`, `color: white` — OK |
| **PWA footer toast** | `pwa/install-footer.css` | `background: #2c3e50`, `color: white` — fixo; em light pode estar OK |
| **Notificações admin** | `admin/index.php` | `alert alert-${type}` — Bootstrap; depende de overrides |
| **showToast (instrutor)** | `instrutor/dashboard-mobile.php` | Função JS customizada — verificar cores do toast |

---

## 6. App Shell (tokens.css, layout.css)

| Item | Arquivo | Problema |
|------|---------|----------|
| **--color-primary** | `assets/css/tokens.css` | `#023A8D` — **sem override em dark** (bloco `prefers-color-scheme: dark` não inclui) |
| **--color-text, --color-bg** | `tokens.css` | Têm override — parcialmente correto |
| **Layout** | `assets/css/layout.css` | Usa `var(--color-primary)` — herda problema acima |
| **Shell** | `app/Views/layouts/shell.php` | Não carrega theme-tokens nem theme-overrides |

---

## 7. Telas e Views (cores hardcoded)

### 7.1 Agenda e Dashboard (app)

| Arquivo | Elementos |
|---------|-----------|
| `app/Views/agenda/show.php` | #dbeafe, #1e40af (badges), var(--color-primary) em links/KM |
| `app/Views/agenda/index.php` | #e0e7ff, #3730a3, #374151, #3b82f6, #111, #666 (modal JS) |
| `app/Views/dashboard/instrutor.php` | #dbeafe, #1e40af, #3b82f6 |
| `app/Views/dashboard/aluno.php` | #dbeafe, #3b82f6, var(--color-primary) |

### 7.2 Login

| Arquivo | Elementos |
|---------|-----------|
| `login.php` | #1A365D, #7f8c8d, #fdf2f2, #e74c3c, etc. — tem bloco dark (756-852) para formulário principal; **pwa-ios-instructions não** |

### 7.3 Admin

| Arquivo | Elementos |
|---------|-----------|
| `admin/index.php` | #fff, #6c757d, #023A8D, #333, #dc3545, etc. — muitos hardcoded |
| `admin/pages/*.php` | Diversos modais e popups com cores fixas |

### 7.4 Instrutor/Aluno (mobile-first)

| Arquivo | Elementos |
|---------|-----------|
| `instrutor/dashboard-mobile.php` | `text-primary`, `text-muted` — **corrigidos** por theme-overrides |
| `aluno/dashboard-mobile.php` | Idem |
| `instrutor/aulas.php` | `text-primary` — corrigido |
| `aluno/aulas.php` | `text-primary`, `bg-primary` — verificar badges |

---

## 8. Componentes com Estilos Injetados (JS)

| Componente | Arquivo | Problema |
|------------|---------|----------|
| **PWA Install Button (login)** | `login.php` addIOSStyles() | Sem dark |
| **iOS Install Modal (shell)** | `assets/js/app.js` | Sem dark |
| **PWA Install Banner modal** | `assets/js/pwa-install-banner.js` | HTML com inline `color: #333`, `#1a365d` — vence CSS |
| **PWA Update Banner** | `pwa/pwa-register.js` | Tem dark — OK |
| **PWA Banner (legado)** | `pwa/pwa-register.js` addBannerStyles() | `background: #2c3e50` — sem dark override |

---

## 9. Meta Tags e Configuração de Tema

| Item | Onde | Problema |
|------|------|----------|
| **theme-color** | shell.php | `#023A8D` fixo — não muda com dark |
| **theme-color** | login.php, mobile-first.php | Script dinâmico que atualiza com `prefers-color-scheme` — **correto** |
| **color-scheme** | login.php, mobile-first.php | `light dark` — **correto** |
| **color-scheme** | shell.php | **Não declarado** — browser pode inferir errado |

---

## 10. Resumo por Categoria

### Crítico (usuário não consegue ler — ex.: popup iOS)

| # | Componente | Arquivo(s) | Ação |
|---|------------|------------|------|
| 1 | Instruções iOS no login | login.php (addIOSStyles) | Adicionar `@media (prefers-color-scheme: dark)` |
| 2 | Modal iOS no shell | assets/js/app.js | Adicionar dark mode ao CSS injetado |
| 3 | Modal iOS install-footer | pwa/install-footer.css | Adicionar `prefers-color-scheme: dark` |
| 4 | Modal instruções pwa-install-banner | pwa-install-banner.js | Remover inline `color: #333`/`#1a365d` ou usar variáveis CSS |
| 5 | tokens.css --color-primary | assets/css/tokens.css | Adicionar override dark |

### Alto (visibilidade reduzida em dark)

| # | Componente | Arquivo(s) |
|---|------------|------------|
| 6 | Modal cluster agenda | app/Views/agenda/index.php (JS) |
| 7 | Modais admin | admin/index.php |
| 8 | Badges/tags agenda/show | app/Views/agenda/show.php |
| 9 | Links e KM Inicial | app/Views/agenda/show.php |
| 10 | theme-color shell | app/Views/layouts/shell.php |

### Médio (verificar em testes)

| # | Componente | Arquivo(s) |
|---|------------|------------|
| 11 | alert/confirm/prompt nativos | Vários |
| 12 | PWA banner legado | pwa/pwa-register.js |
| 13 | Toasts customizados | instrutor/dashboard-mobile, etc. |
| 14 | Popups admin (salas, turmas) | admin/pages/*.php |

### Já Corrigido / OK

| # | Componente | Arquivo(s) |
|---|------------|------------|
| — | PWA Install Overlay | pwa-install-overlay.css |
| — | PWA Update Banner | pwa/pwa-register.js |
| — | theme-overrides (text-primary, etc.) | assets/css/theme-overrides.css |
| — | theme-tokens | assets/css/theme-tokens.css |
| — | Login formulário principal | login.php (bloco dark 756-852) |
| — | mobile-first layout | includes/layout/mobile-first.php |

---

## 11. Checklist de Verificação por Plataforma

### iOS + Dark Mode

- [ ] login.php — instruções "Instalar App" (pwa-ios-instructions)
- [ ] app.js — modal "Instalar Aplicativo"
- [ ] install-footer — modal "Como instalar no iPhone"
- [ ] pwa-install-banner — modal "Instalar no iPhone/iPad"
- [ ] alert/confirm nativos (Safari)

### Android + Dark Mode

- [ ] Mesmos modais de instalação (quando mostrados)
- [ ] App shell (agenda, dashboard)
- [ ] theme-color no shell

### iOS + Light Mode

- [ ] Contraste em fundos claros (texto #333, #666)
- [ ] Modais com fundo branco — geralmente OK

### Android + Light Mode

- [ ] Idem

---

## 12. Arquivos a Alterar (Prioridade)

1. **login.php** — addIOSStyles() — adicionar bloco dark
2. **assets/js/app.js** — ios-install-modal-style — adicionar dark
3. **pwa/install-footer.css** — .pwa-ios-modal* — adicionar dark
4. **assets/js/pwa-install-banner.js** — remover inline colors ou usar classes
5. **assets/css/tokens.css** — --color-primary no dark
6. **app/Views/layouts/shell.php** — theme-color dinâmico, color-scheme
7. **app/Views/agenda/index.php** — modal cluster — variáveis de tema
8. **admin/index.php** — #modal-root, .popup-modal — dark mode

---

**Fim do inventário.**
