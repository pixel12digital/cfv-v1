# 🔍 Diagnóstico: Erros PWA e Por Que Não Aparece "Instalar Aplicativo"

**Data:** 2026-01-26  
**Problema:** Opção de instalação não aparece no perfil/menu

---

## ❌ Erros Comuns e Causas

### 1. **Erro 404 no Service Worker (`sw.js`)**

**Sintoma:**
```
Failed to load resource: the server responded with a status of 404 ()
[SW] Tentando registrar Service Worker
```

**Causas possíveis:**
- Arquivo `sw.js` não está na raiz do DocumentRoot
- Caminho incorreto no registro (`/sw.js` vs `/pwa/sw.js`)
- Arquivo bloqueado pelo `.htaccess`
- Content-Type incorreto (deve ser `application/javascript`)

**Solução:**
- ✅ Verificar se `public_html/sw.js` existe
- ✅ Verificar se `.htaccess` permite acesso a `sw.js`
- ✅ Verificar headers HTTP (Content-Type correto)

---

### 2. **Service Worker Não Está Controlando a Página**

**Sintoma:**
```
[PWA] ⚠️ Service Worker NÃO está controlando a página
[PWA] Isso é necessário para instalação PWA
```

**Causa:**
O Service Worker está registrado, mas não está controlando a página atual. Isso acontece porque:
- O SW foi registrado DEPOIS que a página já carregou
- O SW precisa de um reload para começar a controlar
- O SW está em um scope diferente da página atual

**Solução:**
1. Recarregar a página após o registro do SW
2. Verificar se o scope do SW é `/` (root)
3. Verificar se `clients.claim()` está sendo chamado no SW

---

### 3. **Erro 404 no Manifest (`manifest.json`)**

**Sintoma:**
```
Failed to load resource: the server responded with a status of 404 ()
manifest.json
```

**Causas:**
- Manifest não está acessível via URL
- Caminho incorreto no `<link rel="manifest">`
- Arquivo não existe no servidor

**Solução:**
- ✅ Verificar se `public_html/manifest.json` existe
- ✅ Acessar diretamente: `https://seudominio.com/manifest.json`
- ✅ Verificar caminho no HTML: `<link rel="manifest" href="/manifest.json">`

---

### 4. **Erro 404 nos Ícones**

**Sintoma:**
```
Failed to load resource: the server responded with a status of 404 ()
/icons/1/icon-192x192.png
```

**Causa:**
Ícones referenciados no manifest não existem ou não estão acessíveis.

**Solução:**
- ✅ Verificar se os ícones existem em `/icons/1/`
- ✅ Verificar permissões de acesso aos arquivos
- ✅ Verificar caminhos no manifest.json

---

### 5. **`beforeinstallprompt` Não Dispara**

**Sintoma:**
- Botão "Instalar Aplicativo" não aparece
- Console não mostra `[PWA] beforeinstallprompt disparado`

**Causas (TODAS devem ser atendidas):**

#### ✅ Requisitos Obrigatórios:

1. **Service Worker deve estar controlando a página**
   ```javascript
   navigator.serviceWorker.controller !== null
   ```

2. **Manifest.json deve estar acessível e válido**
   - Deve retornar HTTP 200
   - Deve ter `Content-Type: application/manifest+json`
   - Deve ter campos obrigatórios: `name`, `icons`, `start_url`, `display`

3. **Ícones devem estar acessíveis**
   - Pelo menos um ícone 192x192 e um 512x512
   - Deve retornar HTTP 200

4. **HTTPS ou localhost**
   - PWA só funciona em HTTPS (produção) ou localhost (desenvolvimento)

5. **Usuário não instalou anteriormente**
   - Se já instalou, o evento não dispara novamente

6. **Página foi visitada pelo menos 2 vezes**
   - Chrome requer engajamento mínimo

7. **Display mode correto**
   - `display: "standalone"` ou `"fullscreen"` no manifest

---

## 🔧 Checklist de Diagnóstico

### Passo 1: Verificar Service Worker

**No Console (F12):**
```javascript
// Verificar se SW está registrado
navigator.serviceWorker.getRegistrations().then(regs => {
  console.log('SWs registrados:', regs.length);
  regs.forEach(reg => {
    console.log('Scope:', reg.scope);
    console.log('Active:', reg.active?.state);
  });
});

// Verificar se está controlando
console.log('Controller:', navigator.serviceWorker.controller);
```

**Resultado esperado:**
- ✅ Pelo menos 1 SW registrado
- ✅ `controller !== null`
- ✅ Scope é `/`

---

### Passo 2: Verificar Manifest

**No Console:**
```javascript
fetch('/manifest.json')
  .then(r => r.json())
  .then(m => {
    console.log('✅ Manifest válido:', m);
    console.log('Icons:', m.icons);
    console.log('Start URL:', m.start_url);
  })
  .catch(e => console.error('❌ Erro ao carregar manifest:', e));
```

**Resultado esperado:**
- ✅ HTTP 200
- ✅ JSON válido
- ✅ Campos obrigatórios presentes

---

### Passo 3: Verificar Ícones

**No Console:**
```javascript
const icons = ['/icons/1/icon-192x192.png', '/icons/1/icon-512x512.png'];
icons.forEach(icon => {
  fetch(icon)
    .then(r => {
      console.log(`✅ ${icon}:`, r.status);
    })
    .catch(e => console.error(`❌ ${icon}:`, e));
});
```

**Resultado esperado:**
- ✅ Ambos retornam HTTP 200

---

### Passo 4: Verificar Critérios de Instalabilidade

**No Console:**
```javascript
// Verificar se está em HTTPS ou localhost
const isSecure = location.protocol === 'https:' || 
                 location.hostname === 'localhost' || 
                 location.hostname === '127.0.0.1';
console.log('HTTPS/localhost:', isSecure);

// Verificar se já está instalado
const isInstalled = window.matchMedia('(display-mode: standalone)').matches ||
                    navigator.standalone === true;
console.log('Já instalado:', isInstalled);

// Verificar se SW está controlando
const hasController = navigator.serviceWorker.controller !== null;
console.log('SW controlando:', hasController);
```

**Resultado esperado:**
- ✅ `isSecure === true`
- ✅ `isInstalled === false`
- ✅ `hasController === true`

---

## 🎯 Soluções por Problema

### Problema: SW não está controlando

**Solução:**
1. Recarregar a página após o registro
2. Verificar se `clients.claim()` está no evento `activate` do SW
3. Verificar se o scope do SW é `/`

**Código do SW:**
```javascript
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  
  // CRÍTICO: Reivindicar controle imediatamente
  return self.clients.claim();
});
```

---

### Problema: Manifest não acessível

**Solução:**
1. Verificar se arquivo existe em `public_html/manifest.json`
2. Verificar caminho no HTML: `<link rel="manifest" href="/manifest.json">`
3. Testar acesso direto: `https://seudominio.com/manifest.json`

---

### Problema: Ícones não acessíveis

**Solução:**
1. Verificar se ícones existem em `/icons/1/`
2. Verificar permissões de arquivo (644 ou 755)
3. Verificar caminhos no manifest.json

---

### Problema: `beforeinstallprompt` não dispara

**Solução:**
1. ✅ Garantir que SW está controlando (recarregar página)
2. ✅ Verificar manifest acessível e válido
3. ✅ Verificar ícones acessíveis
4. ✅ Aguardar alguns segundos após carregar a página
5. ✅ Verificar se não está em modo standalone (já instalado)

**Código de captura:**
```javascript
// Capturar CEDO (antes do DOMContentLoaded)
window.addEventListener('beforeinstallprompt', (e) => {
  console.log('[PWA] ✅ beforeinstallprompt capturado!');
  e.preventDefault();
  window.__deferredPrompt = e;
  // Mostrar botão de instalação
});
```

---

## 📋 Checklist Final

Antes de reportar que não funciona, verifique:

- [ ] Service Worker está registrado (`navigator.serviceWorker.getRegistrations().length > 0`)
- [ ] Service Worker está controlando (`navigator.serviceWorker.controller !== null`)
- [ ] Manifest.json acessível (HTTP 200 em `/manifest.json`)
- [ ] Manifest.json válido (JSON parse sem erros)
- [ ] Ícones acessíveis (HTTP 200 em `/icons/1/icon-192x192.png` e `/icons/1/icon-512x512.png`)
- [ ] HTTPS ou localhost (`location.protocol === 'https:' || location.hostname === 'localhost'`)
- [ ] Não está instalado (`!window.matchMedia('(display-mode: standalone)').matches`)
- [ ] Página foi visitada pelo menos 2 vezes
- [ ] `display: "standalone"` no manifest
- [ ] Listener de `beforeinstallprompt` está ativo ANTES do evento disparar

---

## 🚀 Comandos Rápidos de Diagnóstico

Cole no Console (F12) para diagnóstico completo:

```javascript
(async function() {
  console.log('=== DIAGNÓSTICO PWA ===');
  
  // 1. Service Worker
  const regs = await navigator.serviceWorker.getRegistrations();
  console.log('1. SWs registrados:', regs.length);
  console.log('   Controller:', navigator.serviceWorker.controller ? '✅' : '❌');
  
  // 2. Manifest
  try {
    const manifest = await fetch('/manifest.json').then(r => r.json());
    console.log('2. Manifest:', '✅', manifest.name);
    console.log('   Icons:', manifest.icons?.length || 0);
  } catch(e) {
    console.log('2. Manifest:', '❌', e.message);
  }
  
  // 3. Ícones
  const icons = ['/icons/1/icon-192x192.png', '/icons/1/icon-512x512.png'];
  for (const icon of icons) {
    try {
      const res = await fetch(icon);
      console.log(`3. ${icon}:`, res.status === 200 ? '✅' : '❌', res.status);
    } catch(e) {
      console.log(`3. ${icon}:`, '❌', e.message);
    }
  }
  
  // 4. Critérios
  const isSecure = location.protocol === 'https:' || location.hostname === 'localhost';
  const isInstalled = window.matchMedia('(display-mode: standalone)').matches;
  console.log('4. HTTPS/localhost:', isSecure ? '✅' : '❌');
  console.log('   Já instalado:', isInstalled ? '✅' : '❌');
  
  console.log('=== FIM DIAGNÓSTICO ===');
})();
```

---

## 📝 Notas Importantes

1. **O `beforeinstallprompt` só dispara UMA VEZ por sessão**
   - Se você já viu o evento, precisa recarregar a página para ver novamente

2. **Chrome requer engajamento mínimo**
   - Página deve ser visitada pelo menos 2 vezes
   - Usuário deve interagir com a página

3. **Service Worker precisa controlar ANTES do evento**
   - Se o SW não está controlando, o evento não dispara
   - Recarregue a página após o registro do SW

4. **Modo Standalone**
   - Se já está instalado, o evento não dispara
   - Verifique: `window.matchMedia('(display-mode: standalone)').matches`

---

## 🔗 Referências

- [PWA Installability Criteria](https://web.dev/install-criteria/)
- [Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [Web App Manifest](https://developer.mozilla.org/en-US/docs/Web/Manifest)
