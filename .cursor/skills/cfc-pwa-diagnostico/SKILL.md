---
name: cfc-pwa-diagnostico
description: Diagnostica problemas de PWA e instalação no sistema CFC. Usar quando o usuário perguntar por que não aparece "instalar aplicativo", erros 404 no Service Worker, manifest não carrega, ou beforeinstallprompt não dispara.
---

# Diagnóstico PWA - Sistema CFC

Skill para identificar e corrigir problemas de PWA (Service Worker, manifest, ícones, instalação).

## Quando usar

- Usuário pergunta por que não aparece "Instalar aplicativo"
- Erros 404 no console relacionados a `sw.js`, `manifest.json` ou ícones
- Service Worker não está controlando a página
- Evento `beforeinstallprompt` não dispara

## Estrutura PWA neste projeto

| Recurso | Localização | URL esperada |
|---------|-------------|--------------|
| Service Worker (root) | `public_html/sw.js` | `/sw.js` |
| SW principal | `pwa/sw.js` | `/pwa/sw.js` |
| Registro PWA | `pwa/pwa-register.js` | — |
| Manifest | `public_html/manifest.json` | `/manifest.json` |
| Ícones | `public_html/icons/1/` | `/icons/1/icon-192x192.png` etc. |

## Checklist rápido

1. **SW controlando?**  
   `navigator.serviceWorker.controller !== null`  
   Se não: recarregar a página após o registro.

2. **Manifest acessível?**  
   `fetch('/manifest.json')` deve retornar 200 e JSON válido.

3. **Ícones acessíveis?**  
   `/icons/1/icon-192x192.png` e `icon-512x512.png` com 200.

4. **HTTPS ou localhost?**  
   PWA exige um dos dois.

5. **`beforeinstallprompt`**  
   Só dispara se SW estiver controlando, manifest e ícones OK.

## Diagnóstico no Console

```javascript
(async function() {
  const regs = await navigator.serviceWorker.getRegistrations();
  console.log('SWs:', regs.length, 'Controller:', !!navigator.serviceWorker.controller);
  const m = await fetch('/manifest.json').then(r => r.json()).catch(() => null);
  console.log('Manifest:', m ? m.name : 'erro');
})();
```

## Página de diagnóstico

Existe `public_html/diagnostico-pwa-instalacao.html` que roda essas verificações e mostra resultados visuais.

## Documentação detalhada

- `.docs/DIAGNOSTICO_ERROS_PWA_INSTALACAO.md` — erros comuns e soluções
- `.docs/CURSOR_COMO_VER_AGENTES.md` — onde ver MCP/skills/agentes no Cursor
