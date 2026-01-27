# Checklist Overlay PWA — Respostas fechadas (sanity check)

Para validar em produção sem achismo.

---

## 1) Pré-teste obrigatório (produção) — ~30 segundos

No **/aluno/dashboard.php** (View Source), confirmar que existem **todos**:

| Item | O que conferir |
|------|----------------|
| `window.__PWA_INSTALL_URL` | Inline `<script>` antes de `pwa-install-overlay.js` |
| `id="pwa-install-overlay"` **sem** `pwa-overlay-hidden` | Overlay visível por padrão no HTML |
| `#pwa-install-bell` + `#pwa-install-bell-badge` | Sino (dropdown fa-bell) com badge "1" |
| `#pwa-install-header-cta` | Link "Instalar app" no header |
| CSS `.../assets/css/pwa-install-overlay.css` | `<link>` no `<head>` quando overlay ativo |
| JS `.../assets/js/pwa-install-overlay.js` **antes** de `mobile-first.js` | Ordem: overlay JS → mobile-first.js |

✅ **Se algum não aparecer:** não é bug de lógica, é deploy/layout errado (arquivo não atualizado ou não é o `mobile-first.php` que está servindo).

---

## 2) Teste “limpo” (sem throttle e sem instalado)

Para não cair em “já dispensei hoje” ou “está detectando instalado”:

### 2.1 Limpar throttle

- Aba anónima **ou**
- Application → Local Storage → apagar `pwa_overlay_dismissed_date`

### 2.2 Confirmar que NÃO está instalado

No **console**:

- `matchMedia('(display-mode: standalone)').matches` → deve ser **false**
- `navigator.standalone` → deve ser **false**
- `document.referrer` → **não** deve começar com `android-app://`

✅ Se qualquer um for **true**, overlay e sino somem porque o sistema considera já instalado.

---

## 3) Critérios de aceite (o que precisa acontecer)

### A) Não instalado (Chrome Android normal)

- Overlay aparece (visível por padrão e JS não esconde).
- Sino aparece com badge “1”.
- Header CTA “Instalar app” aparece.

### B) “Dispensar por hoje”

- Clicar em **“Dispensar por hoje”** (sino) ou **“Agora não”** (overlay).
- **Resultado esperado:**
  - `localStorage.pwa_overlay_dismissed_date = hoje`
  - Overlay some.
  - Sino continua (persistente) até instalar.
  - Recarregar no mesmo dia: overlay **não** volta.

### C) Instalável (deferredPrompt existe)

- Clicar **“Instalar app”** (overlay) ou **“Instalar aplicativo”** (sino).
- **Resultado esperado:**
  - Prompt nativo abre (`deferredPrompt.prompt()`).
  - Após instalar: `appinstalled` dispara.
  - Overlay some + header CTA some + sino some (`updateBell(false)`).

### D) Não instalável (iOS / WhatsApp in-app)

- Clicar **“Instalar aplicativo”** (sino).
- **Resultado esperado:**
  - Navega para `/install` (instruções).
  - Overlay pode mostrar estado NOT_INSTALLABLE com “Ver instruções”.

---

## 4) Overlay existe no HTML (referência técnica)

- **`window.__PWA_INSTALL_URL`** — Definido em inline `<script>` imediatamente antes do `pwa-install-overlay.js` (em `mobile-first.php`).
- **Markup do overlay** — `id="pwa-install-overlay"`. Classe inicial **sem** `pwa-overlay-hidden` (visível por padrão; JS esconde se instalado ou se dismissed hoje). `role="dialog"`, `aria-modal="true"`, `aria-labelledby="pwa-overlay-title"`.
- **CTA do header** — `<a id="pwa-install-header-cta" ...>Instalar app</a>` dentro do bloco `<?php if (!empty($showPwaInstallOverlay)): ?>`.
- **Sino (bell)** — `<div id="pwa-install-bell">` (dropdown com ícone fa-bell, badge e item “Instalar aplicativo”), no mesmo bloco condicional do header.
- **Carga de assets** — `<link rel="stylesheet" href="…/assets/css/pwa-install-overlay.css">` no `<head>` quando `$showPwaInstallOverlay`; `<script src="…/assets/js/pwa-install-overlay.js">` **antes** de `mobile-first.js`.

Se não existir: conferir deploy, arquivo servido (dashboard vs outro layout) e se o layout incluído é `mobile-first.php`.

---

## 5) Chave exata do localStorage (throttle)

- **Chave:** `pwa_overlay_dismissed_date`
- **Valor:** string `'YYYY-MM-DD'` (data em que o usuário clicou “Agora não” ou Escape).
- **Uso:** se `localStorage.getItem('pwa_overlay_dismissed_date') === hoje` → overlay não aparece; o sino/CTA continuam visíveis quando não instalado.

Para testar “limpo”: aba anónima ou limpar storage do site (Application → Local Storage → clear).

---

## 6) Detecção “instalado” (console)

- `matchMedia('(display-mode: standalone)').matches` → true em standalone.
- `navigator.standalone` → true no iOS instalado (adicionado à tela de início).
- `document.referrer.startsWith('android-app://')` ou `document.referrer.indexOf('android-app://') === 0` → true quando aberto a partir do ícone Android.

Se algum for true → overlay e CTA/sino ficam ocultos.

---

## 7) Onde o JS do overlay é carregado (mobile-first.php)

- **Posição:** **antes** de `mobile-first.js`.
- **Trecho:** após o bloco de registro do Service Worker, dentro de `<?php if (!empty($showPwaInstallOverlay)): ?>`:
  - `<script>window.__PWA_INSTALL_URL = <?php echo json_encode($pwaInstallUrlScript); ?>;</script>`
  - `<script src="…/assets/js/pwa-install-overlay.js"></script>`
- **Depois:** `<script src="…/assets/js/mobile-first.js"></script>` (JavaScript Mobile-First).

Ordem: overlay JS → mobile-first.js → page JS (se houver).

---

## 8) Overlay: “aparece por padrão” ou “só via JS”

- **Por padrão:** o overlay é **visível por padrão** no HTML (sem classe `pwa-overlay-hidden` e com `aria-hidden="false"` no markup inicial).
- O **JS** decide: esconde se instalado; esconde se `pwa_overlay_dismissed_date === hoje`; caso contrário mantém visível e escolhe estado INSTALLABLE / NOT_INSTALLABLE.
- Se o JS falhar ou não carregar, o usuário continua vendo o overlay e pode usar “Ver instruções” (link para `/install`).
