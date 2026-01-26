# 🔍 Script de Validação SW 1.0.10 - Execute no Console

## Como Usar

1. Abrir DevTools (F12) → Console
2. Colar o script abaixo
3. Pressionar Enter
4. Verificar resultados

---

## Script Completo

```javascript
(async function validarSW() {
    console.log('🔍 ===== VALIDAÇÃO SW 1.0.10 =====\n');
    
    // 1. Verificar versão do SW ativo
    console.log('1️⃣ VERIFICANDO VERSÃO DO SW ATIVO...');
    if (navigator.serviceWorker.controller) {
        const swURL = navigator.serviceWorker.controller.scriptURL;
        console.log('   ✅ SW está controlando:', swURL);
        
        // Buscar versão no código do SW
        try {
            const swResponse = await fetch(swURL);
            const swText = await swResponse.text();
            
            if (swText.includes('cfc-v1.0.10')) {
                console.log('   ✅ SW contém cfc-v1.0.10');
            } else if (swText.includes('cfc-v1.0.9')) {
                console.log('   ❌ SW ainda contém cfc-v1.0.9 (VERSÃO ANTIGA!)');
            } else {
                console.log('   ⚠️ Não encontrou versão no SW');
            }
        } catch (e) {
            console.log('   ⚠️ Erro ao buscar SW:', e.message);
        }
    } else {
        console.log('   ⚠️ SW não está controlando ainda');
    }
    
    // 2. Verificar registros
    console.log('\n2️⃣ VERIFICANDO REGISTROS...');
    const regs = await navigator.serviceWorker.getRegistrations();
    console.log(`   Encontrados ${regs.length} registro(s):`);
    regs.forEach((reg, idx) => {
        const sw = reg.active || reg.installing || reg.waiting;
        console.log(`   SW ${idx + 1}:`, {
            scope: reg.scope,
            state: sw?.state,
            scriptURL: sw?.scriptURL
        });
    });
    
    // 3. Verificar cache storage
    console.log('\n3️⃣ VERIFICANDO CACHE STORAGE...');
    const cacheNames = await caches.keys();
    console.log(`   Encontrados ${cacheNames.length} cache(s):`);
    cacheNames.forEach(name => {
        if (name.includes('cfc-v1.0.10')) {
            console.log(`   ✅ ${name} (VERSÃO CORRETA)`);
        } else if (name.includes('cfc-v1.0.9')) {
            console.log(`   ❌ ${name} (VERSÃO ANTIGA - DELETAR!)`);
        } else {
            console.log(`   ℹ️ ${name}`);
        }
    });
    
    // 4. Verificar CSS versionado
    console.log('\n4️⃣ VERIFICANDO CSS VERSIONADO...');
    const cssLinks = Array.from(document.querySelectorAll('link[href*="theme-overrides"]'));
    if (cssLinks.length > 0) {
        cssLinks.forEach(link => {
            const href = link.href;
            if (href.includes('?v=1.0.10')) {
                console.log(`   ✅ CSS com versionamento: ${href}`);
            } else if (href.includes('?v=')) {
                console.log(`   ⚠️ CSS com versionamento antigo: ${href}`);
            } else {
                console.log(`   ❌ CSS SEM versionamento: ${href}`);
            }
        });
    } else {
        console.log('   ⚠️ Nenhum link theme-overrides encontrado');
    }
    
    // 5. Testar rota autenticada (simular)
    console.log('\n5️⃣ VERIFICANDO BLOQUEIO DE ROTAS AUTENTICADAS...');
    console.log('   ℹ️ Navegue para /admin/ ou /instrutor/dashboard.php');
    console.log('   ℹ️ No console, deve aparecer: [SW] 🔒 Rota autenticada - SEM cache');
    
    console.log('\n✅ ===== VALIDAÇÃO CONCLUÍDA =====');
    console.log('\n📋 PRÓXIMOS PASSOS:');
    console.log('   1. Se SW ainda é 1.0.9 → Fazer deploy do sw.js atualizado');
    console.log('   2. Se cache antigo existe → Deletar em Application → Cache Storage');
    console.log('   3. Se CSS sem versionamento → Verificar HTML (admin/index.php, instrutor/dashboard.php)');
    console.log('   4. Fazer Unregister → Clear Storage → Reload 2x');
})();
```

---

## Validação Manual (Passo a Passo)

### Passo 1: Verificar SW em Produção

**Acessar diretamente:**
```
https://painel.cfcbomconselho.com.br/sw.js
```

**O que deve aparecer:**
- Primeiras linhas devem conter: `const CACHE_VERSION = 'cfc-v1.0.10';`
- OU: `importScripts('/pwa/sw.js');` (se for wrapper)

**Se aparecer `cfc-v1.0.9`:**
- ❌ SW em produção ainda está antigo
- ✅ **Ação:** Verificar se deploy copiou o arquivo correto

---

### Passo 2: Verificar Wrapper na Raiz

**Acessar:**
```
https://painel.cfcbomconselho.com.br/sw.js
```

**Se for wrapper, deve aparecer:**
```javascript
importScripts('/pwa/sw.js');
```

**Então verificar:**
```
https://painel.cfcbomconselho.com.br/pwa/sw.js
```

**Deve conter:**
```javascript
const CACHE_VERSION = 'cfc-v1.0.10';
```

---

### Passo 3: Limpar e Recarregar

1. DevTools → Application → Service Workers
2. **Unregister** (se houver)
3. Application → Clear Storage → **Clear site data**
4. Recarregar página (Ctrl+Shift+R)
5. Recarregar novamente (Ctrl+Shift+R)

---

### Passo 4: Verificar Logs no Console

**Deve aparecer:**
```
[SW] Service Worker cfc-v1.0.10 carregado
[SW] Instalando versão cfc-v1.0.10
[SW] Ativando versão cfc-v1.0.10
[SW] ✅ Service Worker ativado e controlando todas as páginas
```

**NÃO deve aparecer:**
```
[SW] Service Worker cfc-v1.0.9 carregado
[SW] Falha ao cachear /admin/
[SW] Falha ao cachear /instrutor/dashboard.php
```

---

### Passo 5: Testar Rota Autenticada

1. Navegar para `/admin/` ou `/instrutor/dashboard.php`
2. Console → Procurar: `[SW] 🔒 Rota autenticada - SEM cache`
3. Network → Verificar que requisição vai para rede (não cache)

---

## Checklist de Validação Final

Após executar o script e seguir os passos:

- [ ] SW ativo é `cfc-v1.0.10` (não `1.0.9`)
- [ ] Não aparecem erros de cachear `/admin/` ou `/instrutor/dashboard.php`
- [ ] Ao navegar em rota autenticada, aparece log `🔒 Rota autenticada`
- [ ] CSS carregado com `?v=1.0.10` no Network
- [ ] Cache Storage não tem `cfc-v1.0.9` (apenas `cfc-v1.0.10`)

**Se todos os itens estiverem ✅ → SW está correto e ativo!**
