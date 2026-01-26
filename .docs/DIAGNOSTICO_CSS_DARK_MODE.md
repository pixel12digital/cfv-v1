# 🔍 Diagnóstico CSS Dark Mode - Guia Completo

## Objetivo
Parar de "chutar CSS" e confirmar se o app está vendo o CSS novo.

---

## 📋 Tarefas de Diagnóstico (Ordem Correta)

### 1. ✅ Confirmar Roteamento do /login

**Pergunta:** `/login` usa `login.php` ou outra rota?

**Verificação:**
1. Abrir DevTools (F12) → Network
2. Acessar `https://painel.cfcbomconselho.com.br/login`
3. Verificar no Network:
   - **Request URL:** Deve ser `/login` ou `/login.php`
   - **Status:** 200 OK
   - **Response Headers → Content-Type:** `text/html`

**Resultado Esperado:**
- ✅ Se `Content-Type: text/html` → Está servindo `login.php` diretamente
- ✅ Se redirect → Verificar para onde redireciona

**Arquivo Verificado:**
- `public_html/.htaccess` → Regra: arquivos físicos são servidos diretamente
- `login.php` existe fisicamente → **Confirmado: `/login` serve `login.php`**

---

### 2. ✅ Verificar "View Source" - Login Dark Mode

**Pergunta:** O HTML gerado contém o script "Login Dark Mode"?

**Verificação:**
1. Acessar `https://painel.cfcbomconselho.com.br/login`
2. Clicar com botão direito → **"Ver código-fonte da página"** (View Source)
3. Procurar por: `[Login Dark Mode]` ou `login-dark-mode-fix`

**Resultado Esperado:**
```html
<!-- Deve encontrar: -->
<script>
    console.log('[Login Dark Mode] 🔍 Script de diagnóstico carregado');
    ...
</script>

<style id="login-dark-mode-fix">
    @media (prefers-color-scheme: dark) {
        ...
    }
</style>
```

**Se NÃO encontrar:**
- ❌ O arquivo `login.php` não está sendo servido
- ❌ Há um cache intermediário (proxy/CDN)
- ❌ O arquivo foi modificado incorretamente

---

### 3. ✅ Verificar Service Worker e Cache

**Pergunta:** O PWA está servindo HTML/CSS do cache?

**Verificação no DevTools:**

#### 3.1. Application → Service Workers
1. Abrir DevTools (F12) → **Application** → **Service Workers**
2. Verificar status:
   - ✅ **Status:** `activated and is running`
   - ✅ **Source:** `/sw.js`
   - ⚠️ Se houver "Update" → Clicar em **"Update"**
   - ⚠️ Se houver "Skip Waiting" → Clicar em **"Skip Waiting"**
   - ⚠️ Se necessário → Clicar em **"Unregister"** para limpar

#### 3.2. Application → Cache Storage
1. DevTools → **Application** → **Cache Storage**
2. Verificar caches:
   - `cfc-cache-cfc-v1.0.10` (versão atual)
   - Caches antigos (ex: `cfc-cache-cfc-v1.0.9`)
3. **Ação:** Clicar com botão direito → **Delete** em caches antigos

#### 3.3. Network → Disable Cache
1. DevTools → **Network**
2. ✅ Marcar checkbox **"Disable cache"**
3. Recarregar página (Ctrl+Shift+R ou Cmd+Shift+R)
4. Verificar se CSS carrega com `?v=1.0.10`

---

### 4. ✅ Verificar CSS Carregado

**Pergunta:** O CSS `theme-overrides.css` está sendo carregado com versionamento?

**Verificação:**
1. DevTools → **Network** → Filtrar por `theme-overrides`
2. Verificar:
   - ✅ **Request URL:** `/assets/css/theme-overrides.css?v=1.0.10`
   - ✅ **Status:** 200 OK
   - ✅ **Size:** Deve ser > 0 (não 0 bytes)
   - ✅ **Type:** `text/css`

**Se aparecer do cache:**
- Verificar **Size** → Se for `(from disk cache)` ou `(from memory cache)`
- Limpar cache do Service Worker (passo 3.2)
- Recarregar com "Disable cache" (passo 3.3)

---

### 5. ✅ Verificar Console - Logs Dark Mode

**Pergunta:** Os logs `[Login Dark Mode]` aparecem no console?

**Verificação:**
1. DevTools → **Console**
2. Limpar console (ícone de lixeira)
3. Recarregar página `/login`
4. Procurar por:
   ```
   [Login Dark Mode] 🔍 Script de diagnóstico carregado
   [Login Dark Mode] 🔍 Iniciando detecção de dark mode...
   [Login Dark Mode] 📱 prefers-color-scheme: dark = true/false
   ```

**Se NÃO aparecer:**
- ❌ O script não está sendo executado
- ❌ O arquivo `login.php` não contém o script
- ❌ Há um erro JavaScript bloqueando a execução

---

### 6. ✅ Verificar CSS Aplicado

**Pergunta:** Os estilos dark mode estão sendo aplicados?

**Verificação:**
1. DevTools → **Elements** → Selecionar `<body>` ou `.login-container`
2. Painel direito → **Computed** ou **Styles**
3. Verificar:
   - ✅ `background-color` deve ser `#0f172a` ou `#1e293b` (dark)
   - ✅ `color` deve ser `#f1f5f9` (branco)
   - ✅ Verificar se há regras `@media (prefers-color-scheme: dark)`

**Se não aparecer:**
- Verificar se dispositivo está em dark mode
- Verificar se `prefers-color-scheme: dark` está ativo
- Verificar se há CSS com maior especificidade sobrescrevendo

---

## 🔧 Correções Aplicadas

### 1. ✅ Service Worker - Não Cachear Rotas Autenticadas

**Arquivo:** `pwa/sw.js`

**Mudanças:**
- ❌ Removido `/admin/` e `/instrutor/dashboard.php` do `APP_SHELL`
- ✅ Adicionado `AUTHENTICATED_ROUTES` com todas as rotas autenticadas
- ✅ Função `isAuthenticatedRoute()` verifica e bloqueia cache
- ✅ Rotas autenticadas sempre vão para a rede (sem cache)

**Rotas que NÃO são mais cacheadas:**
- `/admin/`
- `/admin/index.php`
- `/admin/dashboard.php`
- `/instrutor/dashboard.php`
- `/aluno/dashboard.php`
- Todas as páginas em `/admin/pages/`, `/instrutor/pages/`, `/aluno/pages/`

**O que AINDA é cacheado (apenas estáticos):**
- ✅ CSS/JS de CDN (Bootstrap, Font Awesome)
- ✅ Assets estáticos (`/assets/css/`, `/assets/js/`, `/assets/img/`)
- ✅ Ícones PWA (`/pwa/icons/`)

---

### 2. ✅ Versionamento CSS

**Arquivos Modificados:**
- `login.php` → Já tinha `?v=<?php echo filemtime(...) ?>`
- `admin/index.php` → Adicionado `?v=1.0.10`
- `instrutor/dashboard.php` → Adicionado `?v=1.0.10`

**Versão Atual:** `1.0.10`

**Como atualizar:**
1. Modificar CSS
2. Atualizar versão em todos os arquivos que referenciam `theme-overrides.css`
3. Atualizar `CACHE_VERSION` no `pwa/sw.js`

---

## 📝 Checklist de Diagnóstico

Use este checklist para cada deploy:

- [ ] 1. Verificar roteamento `/login` → `login.php`
- [ ] 2. View Source → Procurar `[Login Dark Mode]`
- [ ] 3. Application → Service Workers → Status ativo
- [ ] 4. Application → Cache Storage → Limpar caches antigos
- [ ] 5. Network → Disable cache → Recarregar
- [ ] 6. Network → Verificar `theme-overrides.css?v=1.0.10`
- [ ] 7. Console → Verificar logs `[Login Dark Mode]`
- [ ] 8. Elements → Verificar CSS aplicado (computed styles)
- [ ] 9. Testar em dispositivo físico (Android/iOS)
- [ ] 10. Verificar se dark mode funciona após limpar cache

---

## 🚨 Problemas Comuns e Soluções

### Problema: CSS não atualiza mesmo após deploy

**Solução:**
1. Limpar cache do Service Worker (Application → Service Workers → Unregister)
2. Limpar Cache Storage (Application → Cache Storage → Delete all)
3. Recarregar com "Disable cache" (Network → Disable cache)
4. Verificar se versão do CSS está correta (`?v=1.0.10`)

### Problema: Logs `[Login Dark Mode]` não aparecem

**Solução:**
1. Verificar View Source → Procurar script
2. Verificar Console → Filtrar por `[Login Dark Mode]`
3. Verificar se há erros JavaScript bloqueando
4. Verificar se `login.php` contém o script (linhas 858-915)

### Problema: Dark mode não aplica visualmente

**Solução:**
1. Verificar se dispositivo está em dark mode
2. Verificar Elements → Computed → `background-color` e `color`
3. Verificar se há CSS inline sobrescrevendo
4. Verificar se `@media (prefers-color-scheme: dark)` está ativo

---

## ✅ Status Atual

- ✅ Service Worker corrigido (não cacheia rotas autenticadas)
- ✅ Versionamento CSS adicionado (`v=1.0.10`)
- ✅ Rotas autenticadas sempre vão para rede
- ✅ Apenas assets estáticos são cacheados

**Próximo passo:** Testar em produção após deploy.
