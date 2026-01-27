# Diagnóstico — “Overlay/sino não aparece no dashboard do aluno”

**Sintoma:** Em produção, em `/aluno/dashboard.php` logado como aluno, não aparecem overlay full-screen, CTA “Instalar app” no header nem sino com badge “1”. No código, o mobile-first.php tem o bloco condicional completo e o overlay está fail-open (sem `pwa-overlay-hidden`).

**Objetivo:** Fechar a causa raiz com prova. Não implementar novas features; apenas diagnosticar com evidência.

---

## 1) Prova #1 — HTML real servido (produção)

No ambiente onde falha, coletar o **View Source** real de `/aluno/dashboard.php` e anotar presença/ausência de:

| Item | Presente? (sim/não) | Trecho ou observação |
|------|---------------------|------------------------|
| `window.__PWA_INSTALL_URL` | | |
| `<div id="pwa-install-overlay"` ... (sem `pwa-overlay-hidden`) | | |
| `id="pwa-install-header-cta"` | | |
| `id="pwa-install-bell"` + `id="pwa-install-bell-badge"` | | |
| `<link ... pwa-install-overlay.css>` | | |
| `<script ... pwa-install-overlay.js>` **antes** de `mobile-first.js` | | |

**Se algum NÃO existir no View Source:**  
→ Causa provável **(A) layout/deploy errado** (arquivo diferente em produção, cache, include apontando para outro layout, ou `$showPwaInstallOverlay` nunca true). Identificar qual arquivo/layout está sendo servido (ex.: outro mobile-first, outra raiz, CDN).

**Se existir comentário de debug:** procurar `<!-- PWA_OVERLAY_ACTIVE=1 -->` (só aparece com `?pwa_debug=1`). Isso confirma que o bloco condicional do overlay foi executado no servidor.

---

## 2) Prova #2 — Network (assets carregaram ou 404?)

No **DevTools > Network** (produção), recarregar a página e anotar:

| Recurso | Status HTTP | URL completa observada |
|---------|-------------|-------------------------|
| `pwa-install-overlay.css` | | |
| `pwa-install-overlay.js` | | |

- Confirmar que **não** há 404, 301 inesperado nem bloqueio (CORS, mixed content).
- Confirmar que o **caminho base** (APP_URL / basePath) está correto para o ambiente.

**Se 404 ou blocked:**  
→ Causa provável **(B) assets 404/path errado** (deploy não publicou os arquivos, ou basePath/APP_URL incorreto em produção).

---

## 3) Prova #3 — DOM existe mas está invisível (CSS/JS)

Se no View Source o overlay/sino **existem**, inspecionar no **DevTools > Elements**:

### 3.1 Overlay

- O elemento `#pwa-install-overlay` existe no DOM?
- Em **Computed** (ou Styles aplicados), anotar:
  - `display` (none / flex / block / …)
  - `visibility`
  - `opacity`
  - `z-index`
  - `position` (deve ser `fixed` para o overlay)

Verificar se algum CSS global (tema, mobile-first.css, Bootstrap) está sobrescrevendo e “matando” o overlay (ex.: `display:none` ou `visibility:hidden` em regra mais específica).

**Se display/visibility/opacity ocultam o overlay:**  
→ Causa provável **(C) CSS ocultando**.

### 3.2 Sino

- O elemento `#pwa-install-bell` existe no DOM?
- Está oculto por `display:none` ou classe aplicada pelo JS (ex.: `d-none`)?
- Em Computed, anotar `display` e classes relevantes.

**Se o sino está no DOM mas invisível por CSS/classes:**  
→ Também aponta para **(C) CSS ocultando** ou para o JS ter aplicado ocultação (ver Prova #4).

---

## 4) Prova #4 — Regras que escondem por “instalado” ou “dispensado hoje”

Coletar e anotar:

| Dado | Valor / resultado |
|------|--------------------|
| `localStorage.getItem('pwa_overlay_dismissed_date')` | (ex.: `"2025-01-27"` ou `null`) |
| Data “hoje” no navegador (YYYY-MM-DD) | |
| `matchMedia('(display-mode: standalone)').matches` | true / false |
| `navigator.standalone` | true / false / undefined |
| `document.referrer` começa com `android-app://`? | sim / não |

Conclusão da lógica:

- Se **“instalado”** (standalone OU iOS standalone OU referrer android-app) → o script trata como instalado e **não** mostra overlay, CTA nem sino.
- Se **“dispensado hoje”** (`pwa_overlay_dismissed_date === hoje`) → overlay não aparece; CTA e sino **continuam** visíveis (a menos que também estejam ocultos por outro motivo).

**Se “instalado” for true indevidamente** (ex.: em Chrome normal, não standalone):  
→ Causa provável **(D) JS concluindo “installed” indevidamente** (detecção excessiva ou ambiente in-app/embedded).

**Se “dispensado hoje” e o usuário realmente dispenseu hoje:**  
→ Causa provável **(E) throttle/dismissed já aplicado** (overlay some por design; sino/CTA deveriam continuar visíveis—se não aparecem, pode haver (B) ou (C) em conjunto).

**Importante:** Repetir o mesmo checklist em **aba anónima** e em **navegador normal** e comparar (localStorage e sessão podem diferir).

---

## 5) Instrumentação mínima (só com `?pwa_debug=1`)

Se as provas #1–#4 não fecharem a causa, usar **modo debug** apenas quando a URL tiver `?pwa_debug=1` (ex.: `/aluno/dashboard.php?pwa_debug=1`).

### 5.1 No HTML (mobile-first.php)

Quando `$showPwaInstallOverlay` está ativo **e** há `?pwa_debug=1`, é injetado no HTML:

- Comentário: `<!-- PWA_OVERLAY_ACTIVE=1 -->`  
Isso confirma no View Source que o bloco condicional do overlay foi avaliado como true no servidor.

### 5.2 No pwa-install-overlay.js

Quando `window.__PWA_DEBUG === 1` (definido pelo layout só se `?pwa_debug=1`), o script faz **um único log** no console após aplicar a lógica, por exemplo:

```
[PWA overlay] installed=? dismissedToday=? deferred=? overlay=? bell=? cta=?
```

- `installed` = resultado de `isPwaInstalled()`
- `dismissedToday` = resultado de `wasDismissedToday()`
- `deferred` = existe `deferredPrompt`?
- `overlay` = `show` ou `hide` (decisão tomada)
- `bell` = `show` ou `hide`
- `cta` = `show` ou `hide`

Com isso dá para ver em uma linha se a decisão de ocultar veio de “instalado”, “dispensado hoje” ou de outro estado.

**Regra:** Nada de alterar SW, manifest ou cache; isto é só diagnóstico.

**Implementação:** O marcador HTML e `window.__PWA_DEBUG` são definidos em `includes/layout/mobile-first.php` quando `$showPwaInstallOverlay` e `$_GET['pwa_debug']` estão ativos. O log no console é emitido em `assets/js/pwa-install-overlay.js` quando `window.__PWA_DEBUG === 1` (após a decisão de show/hide).

---

## 6) Entregável obrigatório (preencher nesta ordem)

Usar este bloco ao reportar o resultado do diagnóstico.

---

### URL e contexto

- **URL exata usada no teste:** (ex.: `https://seudominio.com/aluno/dashboard.php`)
- **Dispositivo/ambiente:** (ex.: Android Chrome / iOS Safari / WhatsApp in-app / PWA instalado / desktop Chrome)

---

### Prova #1 — View Source

- **Print ou trecho do View Source** mostrando presença ou ausência dos 6 itens:
  - `window.__PWA_INSTALL_URL`
  - `id="pwa-install-overlay"` (sem `pwa-overlay-hidden`)
  - `id="pwa-install-header-cta"`
  - `id="pwa-install-bell"` + `id="pwa-install-bell-badge"`
  - `<link ... pwa-install-overlay.css>`
  - `<script ... pwa-install-overlay.js>` antes de `mobile-first.js`

- **Todos presentes?** sim / não  
- **Se não:** quais faltam e trecho (ou print) relevante.

---

### Prova #2 — Network

- **Print do Network** (ou tabela) com status HTTP de:
  - `pwa-install-overlay.css` → status ___
  - `pwa-install-overlay.js` → status ___
- **Observação:** 404? path alterado? basePath incorreto?

---

### Prova #3 — Elements/Computed (se os nós existirem no DOM)

- **`#pwa-install-overlay`:** existe no DOM? sim / não  
  - Se sim: `display` = ___, `visibility` = ___, `position` = ___
- **`#pwa-install-bell`:** existe no DOM? sim / não  
  - Se sim: `display` = ___, classes que ocultam = ___
- **Print do Elements/Computed** do overlay e do sino (se existirem).

---

### Prova #4 — localStorage e “instalado”

- **`localStorage.pwa_overlay_dismissed_date`** = ___ (valor ou “não existe”)
- **Data do teste (hoje):** YYYY-MM-DD = ___
- **Detecção “instalado”:**
  - `matchMedia('(display-mode: standalone)').matches` = ___
  - `navigator.standalone` = ___
  - `document.referrer` começa com `android-app://`? ___
- **Resultado do script:** concluiu “INSTALLED” e ocultou tudo? sim / não. Concluiu “DISMISSED_TODAY” e ocultou só o overlay? sim / não.
- **Teste em aba anónima:** resultado igual ou diferente? ___

---

### Conclusão — UMA causa principal

Assinalar **uma** opção:

- **(A) layout/deploy errado** — View Source não contém o bloco overlay/sino/scripts; outro layout ou versão antiga está sendo servida.
- **(B) assets 404/path errado** — CSS ou JS do overlay em 404 ou path incorreto; arquivos não publicados ou basePath/APP_URL errado.
- **(C) CSS ocultando** — Overlay/sino existem no DOM mas ficam invisíveis por `display`/`visibility`/`opacity` ou regras globais.
- **(D) JS concluindo “installed” indevidamente** — Detecção standalone/referrer marca como instalado sem estar em PWA instalado; overlay/CTA/sino são ocultados por isso.
- **(E) throttle/dismissed já aplicado** — `pwa_overlay_dismissed_date` é hoje; overlay some por desenho; sino/CTA deveriam continuar visíveis (se não estão, ver (B) ou (C)).

**Causa assinalada:** ( )

---

*Documento para diagnóstico de “overlay/sino não aparece” em produção. Sem alteração de SW/manifest/cache.*
