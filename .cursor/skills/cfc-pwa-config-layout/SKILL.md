---
name: cfc-pwa-config-layout
description: Auxilia em configurações e layout PWA do sistema CFC. Usar quando o usuário pedir para configurar PWA, ajustar manifest, ícones, tema, cores, botão instalar, footer de instalação, meta tags theme-color, layout mobile-first, ou onde colocar link do manifest.
---

# PWA: Configurações e Layout - Sistema CFC

Skill para ajudar a configurar e ajustar PWA (manifest, ícones, tema, layout, botões de instalação).

## Quando usar

- Configurar ou alterar manifest (nome, cores, start_url, ícones)
- Ajustar tema/cores do PWA (theme_color, background_color)
- Definir ou trocar ícones (tamanhos, maskable)
- Colocar ou alterar botão "Instalar aplicativo" (footer, menu, layout)
- Ajustar meta tags (theme-color, apple-mobile-web-app)
- Layout mobile-first ou responsivo do PWA
- Onde incluir `<link rel="manifest">` e scripts de registro

---

## 1. Configurações do Manifest

### Arquivos de manifest

| Uso | Arquivo | URL/caminho |
|-----|---------|-------------|
| Raiz (público) | `public_html/manifest.json` | `/manifest.json` |
| Dinâmico (PHP) | `public_html/pwa-manifest.php` | `/pwa-manifest.php` |
| Por perfil | `pwa/manifest-instrutor.json`, `pwa/manifest-aluno.json` | conforme login |

### Campos principais (manifest.json)

```json
{
  "name": "CFC Sistema de Gestão",
  "short_name": "CFC Sistema",
  "description": "Sistema de gestão para Centros de Formação de Condutores",
  "start_url": "/dashboard",
  "scope": "/",
  "display": "standalone",
  "orientation": "portrait-primary",
  "theme_color": "#023A8D",
  "background_color": "#ffffff",
  "icons": [
    { "src": "/icons/1/icon-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "any maskable" },
    { "src": "/icons/1/icon-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "any maskable" }
  ]
}
```

- **theme_color:** cor da barra de status/UI (ex.: `#023A8D`)
- **background_color:** fundo da splash/tela de carregamento
- **display:** `standalone` (app) ou `browser`
- **start_url:** URL ao abrir o app instalado
- Ícones: usar caminhos absolutos (`/icons/...`) para funcionar em qualquer rota

### Manifest dinâmico (pwa-manifest.php)

Usado quando o manifest depende de host ou base path. Retorna JSON com `Content-Type: application/manifest+json`. Não depender de banco/sessão; funções helpers devem ser isoladas no próprio arquivo.

---

## 2. Ícones e temas

### Onde ficam os ícones

| Local | Uso |
|-------|-----|
| `public_html/icons/1/` | Ícones por CFC (ex.: icon-192x192.png, icon-512x512.png) |
| `pwa/icons/` | Ícones genéricos e variantes (icon-192.png, icon-512.png, maskable) |

### Tamanhos mínimos para instalabilidade

- 192x192 (qualquer)
- 512x512 (qualquer ou maskable)
- Opcional: maskable com ~20% de padding seguro

### Cores do tema (padrão CFC)

- **theme_color:** `#023A8D`
- **background_color:** `#ffffff` (ou `#2c3e50` em dark)
- Manter consistência com `<meta name="theme-color" content="...">` no HTML.

---

## 3. Layout e meta tags no HTML

### Meta tags PWA (colocar no `<head>`)

```html
<meta name="theme-color" content="#023A8D">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="CFC Sistema">
```

### Link do manifest

```html
<link rel="manifest" href="/manifest.json">
```

Ou dinâmico: `href="/pwa-manifest.php"` ou por perfil (instrutor/aluno).

### Apple Touch Icon

```html
<link rel="apple-touch-icon" href="/icons/1/icon-192x192.png">
<link rel="apple-touch-icon" sizes="152x152" href="/icons/1/icon-192x192.png">
<link rel="apple-touch-icon" sizes="180x180" href="/icons/1/icon-192x192.png">
```

Ajustar caminho se os ícones estiverem em `pwa/icons/` ou outro diretório.

---

## 4. Layout do botão “Instalar aplicativo”

### Onde o botão aparece neste projeto

- **Login:** footer via `pwa/install-footer.js` + `pwa/install-footer.css`
- **Admin/Shell:** possível botão no dropdown do usuário (avatar) via `assets/js/app.js` ou layout
- **Instrutor/Dashboard:** conforme inclusão de `pwa-register.js` e handlers de `beforeinstallprompt`

### Fluxo do install-footer (login)

1. `install-footer.js` escuta `beforeinstallprompt` (e usa `window.__deferredPrompt` se já capturado cedo).
2. Mostra botão somente se houver `deferredPrompt` (Android/desktop) ou sempre em iOS (instruções manuais).
3. Ao clicar: chama `deferredPrompt.prompt()` e trata `userChoice`.
4. Estilos em `pwa/install-footer.css`; manter discreto e acessível.

### Regras de layout para o botão

- Não bloquear conteúdo principal.
- Em mobile: pé da tela ou faixa fixa inferior.
- Esconder quando já instalado: `window.matchMedia('(display-mode: standalone)').matches` ou `navigator.standalone` (iOS).

---

## 5. Service Worker e registro

### Arquivos

- **Root (obrigatório para scope /):** `public_html/sw.js` (pode ser wrapper com `importScripts('/pwa/sw.js')`)
- **Lógica principal:** `pwa/sw.js`
- **Registro:** `pwa/pwa-register.js` (e/ou trecho inline no layout)

### Onde registrar o SW

- Layout principal (ex.: `app/Views/layouts/shell.php`): path via `pwa_asset_path('sw.js')` ou `/sw.js`
- Login / mobile-first: path absoluto `/sw.js` ou `$basePath . '/sw.js'`
- Registrar com `scope: '/'` para cobrir todo o site.

### Headers recomendados para sw.js

No servidor ou `.htaccess`: `Content-Type: application/javascript`, `Service-Worker-Allowed: /`.

---

## 6. Layout mobile-first e responsivo

### Arquivos de layout

- `includes/layout/mobile-first.php` — estrutura base mobile
- `assets/css/mobile-first.css`, `theme-overrides.css`, `theme-tokens.css` — temas e breakpoints
- Garantir que o footer de instalação e qualquer banner PWA não quebrem em telas pequenas (padding, font-size, tap targets ≥ 44px).

### Checklist rápido de layout PWA

- [ ] Manifest com `display`, `theme_color`, `background_color` e ícones válidos
- [ ] `<link rel="manifest">` e meta tags PWA no `<head>` das páginas que precisam ser instaláveis
- [ ] Botão instalar só visível quando `deferredPrompt` existe ou em iOS com instruções
- [ ] Botão instalar oculto em standalone
- [ ] theme-color alinhado ao manifest e à identidade visual

---

## 7. Referências no projeto

| Assunto | Arquivo ou pasta |
|---------|-------------------|
| Manifest estático | `public_html/manifest.json` |
| Manifest dinâmico | `public_html/pwa-manifest.php` |
| Manifest por perfil | `pwa/manifest-instrutor.json`, `pwa/manifest-aluno.json` |
| Footer “Instalar” (login) | `pwa/install-footer.js`, `pwa/install-footer.css` |
| Registro SW e eventos | `pwa/pwa-register.js` |
| SW root | `public_html/sw.js` |
| SW lógica | `pwa/sw.js` |
| Layout shell (admin) | `app/Views/layouts/shell.php` |
| Layout mobile | `includes/layout/mobile-first.php` |
| Diagnóstico PWA | `.docs/DIAGNOSTICO_ERROS_PWA_INSTALACAO.md` |

Use este skill junto com `cfc-pwa-diagnostico` quando o foco for diagnóstico de erros ou “não aparece instalar”.
