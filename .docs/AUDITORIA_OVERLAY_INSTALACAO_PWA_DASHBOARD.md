# Auditoria — Overlay “Instalar App” no dashboard do aluno

**Objetivo do recurso:** No `/aluno/dashboard.php`, mostrar overlay full-screen incentivando instalar o PWA quando o app não estiver instalado. Se estiver instalado (standalone), não mostrar.

**Escopo:** Auditoria + plano. Sem alteração em SW/manifest. Overlay = camada de UX no dashboard.

**Data:** 2025-01-27

---

## 1) Inventário do ecossistema PWA (instalação / handlers)

### 1.1 Arquivos que registram Service Worker, carregam manifest, escutam beforeinstallprompt ou exibem CTA “Instalar”

| Arquivo | O que faz | Eventos/handlers | Risco de conflito |
|---------|-----------|------------------|--------------------|
| **login.php** | Manifest via `<link rel="manifest">`; script **early** no `<head>` captura `beforeinstallprompt` → `e.preventDefault()`, `window.__deferredPrompt = e`, dispara `pwa:beforeinstallprompt`. Carrega depois `pwa-register.js` e `install-footer.js` + container `.pwa-install-footer-container`. | `beforeinstallprompt` (early), `pwa:beforeinstallprompt` (dispatch) | Nenhum no dashboard: login é outra página. |
| **includes/layout/mobile-first.php** | Usado por **aluno/dashboard.php**. Manifest `<link rel="manifest" href="…/pwa/manifest.json">`. Registro de SW inline: `navigator.serviceWorker.register(basePath+'/sw.js', {scope:'/'})`. **Não** carrega pwa-register, install-footer nem script early. | Nenhum `beforeinstallprompt` | Dashboard hoje **não** captura o evento. |
| **pwa/pwa-register.js** | Registra SW; escuta `beforeinstallprompt` → `e.preventDefault()`, `this.deferredPrompt = e`. Usado em **login.php**, **instrutor/dashboard.php**, **admin/index.php**. **Não** é carregado em aluno/dashboard (layout mobile-first). | `beforeinstallprompt`, `appinstalled` | Conflito se for incluído junto com outro listener na mesma página. |
| **pwa/install-footer.js** | Usa `window.__deferredPrompt` ou escuta `pwa:beforeinstallprompt` e `beforeinstallprompt` (backup). Faz `e.preventDefault()` no backup. Renderiza footer “Instalar App”. Só carregado em **login.php** (não no dashboard). | `beforeinstallprompt`, `pwa:beforeinstallprompt`, `appinstalled` | Não conflita com dashboard hoje; dashboard não o carrega. |
| **app/Views/install.php** | Landing `/install`. Manifest implícito pelo layout; script inline: `beforeinstallprompt` → `e.preventDefault()`, `deferredPrompt = e` (variável local). Botão “Instalar app” chama `deferredPrompt.prompt()`. | `beforeinstallprompt` (local) | Página isolada; sem impacto no dashboard. |
| **assets/js/app.js** | Usado pelo **app** (layouts/shell). Variável local `deferredPrompt`; `beforeinstallprompt` → `deferredPrompt = e`. CTA “Instalar Aplicativo” no container do app. | `beforeinstallprompt`, `appinstalled` | Não usado no legado aluno/dashboard. |
| **public_html/diagnostico-pwa-instalacao.html** | Ferramenta de diagnóstico; escuta `beforeinstallprompt` e preenche `window.__deferredPrompt` apenas para debug. | `beforeinstallprompt` | Só em página de diagnóstico. |

Resumo para **aluno/dashboard.php**:
- Carrega apenas **includes/layout/mobile-first.php**.
- Tem manifest e SW; **não** tem nenhum listener de `beforeinstallprompt` nem CTA de instalação via prompt (apenas o link “Instalar app” do banner de primeiro acesso, que leva para `/install`).

---

## 2) Estado “instalado” — como detectar hoje

Trechos atuais no projeto:

- **app/Views/install.php:**  
  `(window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || (window.navigator.standalone === true) || (document.referrer && document.referrer.indexOf('android-app://') === 0)`
- **pwa/install-footer.js:**  
  `matchMedia('(display-mode: standalone)').matches` → instalado;  
  `navigator.standalone === true` → iOS instalado;  
  opcional assíncrono: `getInstalledRelatedApps()`.
- **assets/js/app.js:**  
  `matchMedia('(display-mode: standalone)').matches || navigator.standalone === true`
- **public_html/diagnostico-pwa-instalacao.html:**  
  `matchMedia('(display-mode: standalone)').matches || navigator.standalone === true`

**Método canônico recomendado para “installed” no overlay:**

```js
function isPwaInstalled() {
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return true;
    if (window.navigator.standalone === true) return true;
    if (document.referrer && document.referrer.indexOf('android-app://') === 0) return true;
    return false;
}
```

Opcional: usar `getInstalledRelatedApps()` em ambiente que suporte, para refinar “já instalado” no Chrome Android sem standalone (ex.: usuário abre o site na aba do navegador mas já tem o PWA instalado).

---

## 3) Risco #1 — Múltiplos listeners de beforeinstallprompt

### Quantos listeners existem hoje?

| Página / contexto | Quem escuta | preventDefault? | Onde fica o prompt |
|-------------------|------------|-----------------|---------------------|
| **login.php** | Script early no head | Sim | `window.__deferredPrompt` |
| **login.php** | install-footer.js (backup) | Sim | `window.__deferredPrompt` + `this.deferredPrompt` |
| **login.php** | pwa-register.js | Sim | `this.deferredPrompt` (objeto da classe) |
| **app/Views/install.php** | Script inline | Sim | Variável local `deferredPrompt` |
| **aluno/dashboard.php** (mobile-first) | **Nenhum** | — | — |
| **instrutor/dashboard.php** | pwa-register.js | Sim | `this.deferredPrompt` |
| **admin/index.php** | pwa-register.js | Sim | `this.deferredPrompt` |
| **App (shell)** | app.js | Implícito (não há preventDefault explícito no trecho visto) | Variável local `deferredPrompt` |

### Quem chama preventDefault()?

- login early: sim  
- install-footer (backup): sim  
- pwa-register: sim  
- install.php: sim  

O evento dispara **uma vez por “contexto”** (geralmente por aba/origem). O primeiro listener que faz `preventDefault()` é o que “segura” o prompt; os demais podem não receber o evento ou recebem depois de já ter sido consumido, dependendo da ordem de carregamento.

### Onde o deferredPrompt é guardado?

- **Global:** `window.__deferredPrompt` (login early, install-footer quando usa esse valor).
- **Closure/instância:** em install.php (variável local); em pwa-register e app.js (propriedade de objeto/closure).

### Risco de o dashboard não receber o evento?

**Sim.** Hoje o **aluno/dashboard.php** não carrega nenhum script que escute `beforeinstallprompt`. O usuário que entra direto no dashboard (por exemplo após definir senha e redirect) está em um **novo document**: o evento dessa carga só será disparado se houver listener nessa página. Se o overlay for acrescentado ao dashboard e incluir seu próprio listener (com `preventDefault()` e armazenando o prompt), o dashboard passará a ser capaz de receber e usar o evento naquela carga. Não há “conflito” com login/install nesse doc, pois são outras páginas.

### Mapa de fluxo do evento (resumido)

```
Página                    Scripts carregados              Quem captura beforeinstallprompt    Onde fica o prompt
────────────────────────────────────────────────────────────────────────────────────────────────────────────
login.php                 early (head), pwa-register,     early → __deferredPrompt           window.__deferredPrompt
                          install-footer                  install-footer (backup)             install-footer usa __deferredPrompt
                                                          pwa-register (também escuta)         pwa-register: this.deferredPrompt

aluno/dashboard.php      mobile-first (manifest+SW       Ninguém                            —
(via mobile-first)        + mobile-first.js)

/app/install             (layout da view install)         Script inline da view              Variável local deferredPrompt

instrutor/dashboard      pwa-register                     pwa-register                       this.deferredPrompt
admin/index              pwa-register                     pwa-register                       this.deferredPrompt
```

---

## 4) Onde e como injetar o overlay (sem quebrar o app)

### Página alvo

**aluno/dashboard.php** (arquivo físico, legado). Conteúdo é montado em buffer e depois passado ao layout via `include __DIR__ . '/../includes/layout/mobile-first.php'`, que recebe `$pageContent` e variáveis como `$pageTitle`, `$homeUrl`, etc.

### Onde inserir o markup do overlay

- **Recomendado:** No **final do body** do layout, ou como primeiro filho do body, em **includes/layout/mobile-first.php**, **condicionado a “é dashboard do aluno”** (ex.: variável `$showPwaInstallOverlay` setada em dashboard e repassada ao layout).  
- **Alternativa:** Inserir o bloco do overlay no próprio **aluno/dashboard.php** antes do `include` do layout, dentro do buffer (ex.: após o `ob_start()` e antes do `ob_get_clean()`), garantindo que fique no body quando o layout renderizar `$pageContent`. O layout hoje injeta `$pageContent` num bloco específico do body; o overlay pode ser um outro bloco fixo (ex.: `<div id="pwa-install-overlay">…</div>`) no mesmo nível.

A opção mais limpa é o layout expor um “slot” ou condicional para o overlay (ex.: “se `$showPwaInstallOverlay` e não instalado, imprimir overlay”), e o dashboard setar essa flag, assim o layout continua sendo o único dono da estrutura do body.

### Onde inserir o JS

- **Recomendado:** Script **inline** no layout (mobile-first) condicionado à mesma flag, ou ficheiro **único** tipo `assets/js/pwa-install-overlay.js` (ou `pwa/dashboard-install-overlay.js`) carregado **apenas** quando `$showPwaInstallOverlay` é true, **antes** de `mobile-first.js`, para que o listener de `beforeinstallprompt` seja registrado cedo.  
- **Ordem desejada:** (1) Script que captura `beforeinstallprompt` e monta a lógica do overlay; (2) Bootstrap e demais scripts. O layout hoje já carrega Bootstrap e depois mobile-first.js; o overlay pode ser o primeiro script de “negócio” após o theme-color, para aumentar chance de ver o evento no dashboard.

### Assets do dashboard

- **aluno/dashboard.php** usa **includes/layout/mobile-first.php**.
- Esse layout usa: `pwa/manifest.json`, `assets/css/theme-tokens.css`, `assets/css/mobile-first.css`, `assets/css/theme-overrides.css`, Bootstrap, Font Awesome, `assets/js/mobile-first.js`, SW em `basePath/sw.js`.
- **Não** usa assets do app (app.js, shell, etc.). Ou seja, o overlay deve depender só do que o mobile-first já carrega, ou de um JS/CSS novos incluídos pelo mesmo layout.

### Ordem dos scripts e captura do beforeinstallprompt

- O layout não tem script “early” no head. O **beforeinstallprompt** costuma disparar após o load/idsle, então um script no **início do body** (ou no head) dedicado ao overlay já aumenta a chance de captura nessa página.
- Para máxima garantia no dashboard, o overlay deve registrar seu próprio `beforeinstallprompt` o mais cedo possível nessa página (ex.: primeiro script após meta tags, ou no head), fazer `preventDefault()` e guardar em `window.__deferredPrompt` (ou numa variável module/closure usada só pelo overlay), e usar esse valor no botão “Instalar” do overlay.

---

## 5) Estados e “máquina de estados” do overlay

### Estados mínimos

| Estado | Condição | Comportamento do overlay |
|--------|----------|---------------------------|
| **INSTALLED** | `isPwaInstalled()` true | Overlay nunca aparece. |
| **NOT_INSTALLED + INSTALLABLE** | Não instalado e existe `deferredPrompt` (Android/Chrome elegível) | Overlay aparece com CTA “Instalar” que chama `deferredPrompt.prompt()`. |
| **NOT_INSTALLED + NOT_INSTALLABLE** | Não instalado e não há `deferredPrompt` (iOS, in-app, etc.) | Overlay aparece com texto/instruções e CTA “Abrir no Safari/Chrome” ou link para `/install`. |

### Eventos e transições

| Evento | Ação |
|--------|------|
| `beforeinstallprompt` | Guardar evento em `deferredPrompt`, atualizar UI para INSTALLABLE se ainda não instalado. |
| Clique em “Instalar” (user gesture) | Chamar `deferredPrompt.prompt()`; opcionalmente escutar `userChoice` e, se aceito, considerar transição para INSTALLED após `appinstalled`. |
| `appinstalled` | Marcar instalado (ex.: sessionStorage/localStorage), fechar overlay, não mostrar de novo. |
| Clique em “Cancelar” / “Agora não” | Fechar overlay e aplicar política de throttle (ver seção 6). |

---

## 6) Persistência do “Cancelar” (política e recomendação)

### Opções avaliadas

| Opção | Descrição | Prós | Contras |
|-------|-----------|------|---------|
| **A** | Mostrar a cada carregamento até instalar | Máxima conversão teórica, CTA sempre visível | Alto risco de irritação, sensação de “travamento”, mais suporte e abandono; pior em in-app (WhatsApp). |
| **B** | 1x por sessão + botão fixo no header | Menos intrusivo que A; CTA continua acessível | Usuário que fecha e reabre no mesmo dia pode ver de novo na nova sessão. |
| **C** | 1x por dia (localStorage) + botão fixo no header | Bom equilíbrio: não repete no mesmo dia; CTA sempre no header | Requer implementar contagem/ data (ex.: `pwa_overlay_dismissed_date`). |
| **D** | Mostrar 1x; depois só se clicar “Instalar” no header/menu | Mínima fricção após o primeiro “Agora não” | CTA “Instalar” deve estar muito visível (header/menu); risco de usuário nunca mais ver se o header for pequeno ou escondido. |

### Recomendação final: **C (1x por dia + botão fixo no header)**

- **Objetivo atendido:** “Instalar” continua sempre disponível pelo botão no header; overlay não precisa reaparecer a cada load.
- **Menos irritação:** Quem dispensar “Agora não” não vê o overlay de novo naquele dia, o que reduz sensação de site “travando” e reclamações.
- **In-app (WhatsApp):** Throttle por dia ajuda a não encher a tela em toda abertura do link; mesmo assim o usuário pode instalar pelo header.
- **Suporte / usuários leigos:** Um único overlay por dia é mais fácil de explicar (“você pode fechar e instalar depois pelo botão no topo”) do que um que reaparece a toda hora.
- **Implementação sugerida:**  
  - localStorage: chave `pwa_overlay_dismissed_at` (timestamp ou data em YYYY-MM-DD).  
  - Ao “Cancelar / Agora não”: gravar data atual; fechar overlay.  
  - Na abertura: se `isPwaInstalled()` → não mostrar. Se não instalado e (nunca dispensou **ou** última dispensa foi em dia anterior) → mostrar overlay.  
  - Em todo caso, mostrar **sempre** um botão “Instalar app” no header (ou na barra do layout) quando não instalado, sem throttle.

Resumo: **throttle “1x por dia” + CTA fixo no header** equilibra conversão e uso tranquilo, e atende ao requisito de “não quebrar” e “não irritar”.

---

## 7) iOS e in-app (WhatsApp)

### iOS Safari

- **beforeinstallprompt** não existe.
- Overlay em estado NOT_INSTALLABLE deve mostrar instruções explícitas: “Para instalar: Abra o menu compartilhar (ícone de compartilhar) → ‘Adicionar à Tela de Início’.”
- Opcional: detectar iOS (`/iPad|iPhone|iPod/.test(navigator.userAgent)`) e mostrar esse bloco de texto em vez do botão “Instalar” nativo.

### Android WhatsApp in-app

- **beforeinstallprompt** pode não disparar ou ser restrito dentro do WebView do WhatsApp.
- Overlay NOT_INSTALLABLE deve incluir fallback: “Para instalar, abra este link no **Chrome** (ou **Safari**)”, com botão/link que tenta abrir a URL atual no browser externo (ou envia para `/install` com instruções).  
- Não bloquear o uso do painel: “Cancelar” ou “Abrir no navegador” devem fechar o overlay e deixar o dashboard utilizável.

### Fallback único

- Uma única tela/estado “Não instalável” com:  
  - Texto curto: “Instale o app para acesso rápido.”  
  - **Android:** “Abra no Chrome e use o menu ⋮ → Instalar app.”  
  - **iOS:** “Use o ícone de compartilhar → Adicionar à Tela de Início.”  
  - CTA: “Abrir instruções” ou link para `/install` (página que já trata iOS/Android/desktop).

---

## 8) Acessibilidade do overlay

Checklist proposto:

- [ ] **Contraste:** Texto e botões atendem a contraste mínimo (WCAG 2.1 AA), em tema claro e escuro (respeitando theme-color / prefers-color-scheme do layout).
- [ ] **Botões grandes:** Área de toque ≥ 44×44 px; texto legível.
- [ ] **Texto curto e direto:** Frase principal única; instruções em uma ou duas linhas.
- [ ] **Foco e teclado (desktop):** Overlay recebe foco ao abrir; Tab leva a “Instalar” e “Cancelar”; Escape ou “Cancelar” fecha e devolve foco ao conteúdo anterior.
- [ ] **Fechar de verdade:** “Cancelar” remove o overlay e aplica throttle; não deixa overlay “escondido” prendendo foco ou scroll.
- [ ] **Scroll:** Overlay em posição fixa (ex.: `position: fixed; inset: 0`); conteúdo atrás sem scroll visível durante o overlay; ao fechar, scroll do dashboard volta ao normal.
- [ ] **ARIA (opcional):** `role="dialog"`, `aria-modal="true"`, `aria-labelledby`/`aria-describedby` no overlay; `aria-label` nos botões se o texto visível não for suficiente.

---

## 9) Matriz de testes (esperado)

| Cenário | Overlay | Comportamento esperado |
|---------|---------|-------------------------|
| **Android Chrome, instalável, 1ª visita** | Aparece (ou após throttle: 1x por dia) | Botão “Instalar” usa `deferredPrompt.prompt()`; ao aceitar, `appinstalled` → overlay some. |
| **Android Chrome, já instalado (standalone)** | Não aparece | `isPwaInstalled()` true; overlay nunca é exibido. |
| **Android WhatsApp in-app, não instalado** | Aparece em estado NOT_INSTALLABLE | Instruções “Abrir no Chrome” / link para `/install`; “Cancelar” fecha e mantém uso do painel. |
| **iOS Safari, não instalado** | Aparece em estado NOT_INSTALLABLE | Texto “Adicionar à Tela de Início” / compartilhar; sem botão “Instalar” nativo. |
| **iOS Safari, já instalado (standalone)** | Não aparece | `navigator.standalone === true` ou display-mode standalone. |
| **Desktop Chrome** | Opcional | Pode mostrar overlay ou apenas CTA no header; comportamento definido no plano de implementação. |
| **“Cancelar” + recarregar no mesmo dia** | Não reaparece | localStorage com data do dia; overlay não reabre; botão “Instalar” no header continua visível. |
| **“Cancelar” + próximo dia** | Pode reaparecer 1x | Throttle “1x por dia” permite nova exibição no dia seguinte. |

---

## 10) Plano mínimo de implementação e arquivos a tocar

**Não implementar agora;** apenas plano com base na auditoria.

### Passos sugeridos

1. **Layout mobile-first**
   - Receber variável (ex.: `$showPwaInstallOverlay`) para exibir overlay só no dashboard do aluno.
   - Inserir, no body, markup do overlay (ex.: `<div id="pwa-install-overlay" class="pwa-overlay" aria-hidden="true">…</div>`) condicionado a essa variável.
   - Incluir CSS do overlay (inline ou `assets/css/pwa-install-overlay.css`) apenas quando `$showPwaInstallOverlay` for true.
   - Incluir script do overlay **antes** de mobile-first.js: ou inline (early) ou `assets/js/pwa-install-overlay.js` / `pwa/dashboard-install-overlay.js`, que:
     - Registra listener de `beforeinstallprompt` com `preventDefault()` e guarda em `window.__deferredPrompt` (ou variável do módulo).
     - Usa `isPwaInstalled()`; se true, não mostra overlay.
     - Implementa estados INSTALLABLE / NOT_INSTALLABLE e botões “Instalar” e “Cancelar”.
     - Em “Cancelar”, grava em localStorage `pwa_overlay_dismissed_at` (data em YYYY-MM-DD) e esconde overlay.
     - Na carga, só mostra overlay se não instalado e (nunca dispensou ou dia da última dispensa < hoje).
     - Escuta `appinstalled` para esconder overlay e não mostrar de novo.
     - Garante acessibilidade (foco, Escape, Tab, contraste).

2. **aluno/dashboard.php**
   - Definir `$showPwaInstallOverlay = true` (e repassar ao layout, se o layout usar variáveis vindas do dashboard).
   - Garantir que o layout usado seja o mobile-first e que ele leia essa flag.

3. **Header / barra fixa (mobile-first ou dashboard)**
   - Adicionar botão “Instalar app” visível quando **não** instalado (usando a mesma `isPwaInstalled()` e, se existir, `window.__deferredPrompt` ou lógica do overlay). Esse botão não sofre throttle; pode reutilizar a mesma função de “abrir prompt” ou link para `/install` quando não houver prompt.

4. **Arquivos a tocar (resumo)**

   - `includes/layout/mobile-first.php`: condicional do overlay, markup, ordem de scripts e CSS.
   - `aluno/dashboard.php`: setar flag para mostrar overlay.
   - Novo ficheiro (recomendado): `assets/js/pwa-install-overlay.js` ou `pwa/dashboard-install-overlay.js` (e, se quiser, `assets/css/pwa-install-overlay.css`), ou bloco inline no layout para o overlay.
   - Não alterar: `pwa/sw.js`, manifest, rotas de auth, fluxos legados de login/dashboard.

5. **Testes**

   - Executar a matriz da seção 9 em Android Chrome (instalável / já instalado), Android WhatsApp in-app, iOS Safari (não instalado / instalado), e desktop conforme definido.

---

## Resumo dos riscos e conflitos

- **Múltiplos listeners:** No **dashboard**, hoje não há listener; ao adicionar um único script de overlay que captura `beforeinstallprompt` e faz `preventDefault()`, não há conflito com outros scripts nessa página. Evitar carregar pwa-register/install-footer no mesmo documento do overlay sem unificar quem segura o prompt.
- **Throttle “Cancelar”:** Adotar 1x por dia + CTA fixo no header evita irritação e mantém o objetivo de sempre oferecer “Instalar”.
- **iOS / in-app:** Overlay deve tratar estado NOT_INSTALLABLE com instruções e link para `/install`, sem depender de `beforeinstallprompt`.

---

*Documento gerado para suporte à implementação futura do overlay “Instalar App” em `/aluno/dashboard.php`.*
