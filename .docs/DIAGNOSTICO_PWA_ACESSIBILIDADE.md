# 🔍 Diagnóstico PWA - Problema de Acessibilidade

## ❌ Problema Confirmado

**Evidências do Console:**
1. `HEAD https://painel.cfcbomconselho.com.br/sw.js 404 (Not Found)`
2. `HEAD https://painel.cfcbomconselho.com.br/sw.php 404 (Not Found)`  
3. `Manifest: Line: 1, column: 1, Syntax error. pwa-manifest.php:1`

**Conclusão:** Os arquivos PWA não estão acessíveis publicamente na raiz do DocumentRoot.

---

## 🔍 Investigação Necessária

### A) Prova de "Existência Pública"

Execute no servidor (via SSH) ou localmente:

```bash
# Testar sw.js
curl -i https://painel.cfcbomconselho.com.br/sw.js

# Testar sw.php
curl -i https://painel.cfcbomconselho.com.br/sw.php

# Testar pwa-manifest.php
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php
```

**O que esperar:**
- ✅ **Status 200** para todos
- ✅ **Content-Type correto:**
  - `sw.js` → `application/javascript`
  - `sw.php` → `application/javascript`
  - `pwa-manifest.php` → `application/manifest+json`
- ✅ **Body do manifest** deve começar com `{` (não `<` ou HTML)

**Se retornar HTML:**
- Cole as primeiras ~200 caracteres do body
- Provavelmente será página de login, redirect ou 404 estilizado

---

### B) Prova de "Existência Física"

**No servidor, verificar:**

1. **Qual é o DocumentRoot do subdomínio `painel`?**
   - No painel da Hostinger: **Domínios** → **Subdomínios** → `painel`
   - Verificar onde está apontando

2. **Confirmar se os arquivos existem no DocumentRoot:**
   ```bash
   # Se DocumentRoot for /public_html/
   ls -lah /public_html/sw.js
   ls -lah /public_html/sw.php
   ls -lah /public_html/pwa-manifest.php
   
   # Se DocumentRoot for /public_html/painel/public_html/
   ls -lah /public_html/painel/public_html/sw.js
   ls -lah /public_html/painel/public_html/sw.php
   ls -lah /public_html/painel/public_html/pwa-manifest.php
   ```

**Ponto crítico:** 
- Se o DocumentRoot for `/public_html/` mas os arquivos estão em `/public_html/painel/public_html/`, vai dar 404 para sempre
- Os arquivos DEVEM estar fisicamente no DocumentRoot

---

### C) Roteamento/Rewrite

**Se os arquivos existem fisicamente mas continuam 404:**

O `.htaccess` pode estar capturando as rotas. Verificar:

1. **O `.htaccess` no DocumentRoot permite acesso direto?**
   ```apache
   # Deve ter estas regras ANTES do front controller:
   RewriteRule ^sw\.(js|php)$ - [L]
   RewriteRule ^pwa-manifest\.php$ - [L]
   ```

2. **A regra de "arquivo existe fisicamente" está funcionando?**
   ```apache
   RewriteCond %{REQUEST_FILENAME} -f [OR]
   RewriteCond %{REQUEST_FILENAME} -d
   RewriteRule ^ - [L]
   ```

---

## ✅ Solução Baseada no Diagnóstico

### Cenário 1: Arquivos não estão no DocumentRoot

**Solução:** Copiar arquivos para o DocumentRoot correto

```bash
# Identificar DocumentRoot (via PHP ou painel Hostinger)
# Exemplo: se DocumentRoot for /public_html/

# Copiar arquivos PWA
cp /public_html/painel/public_html/sw.js /public_html/sw.js
cp /public_html/painel/public_html/sw.php /public_html/sw.php
cp /public_html/painel/public_html/pwa-manifest.php /public_html/pwa-manifest.php

# Copiar ícones (se necessário)
cp -r /public_html/painel/public_html/icons /public_html/icons
```

### Cenário 2: Arquivos estão no DocumentRoot mas .htaccess bloqueia

**Solução:** Ajustar `.htaccess` para permitir acesso direto

```apache
# Front Controller Pattern
RewriteEngine On

# 1) Se o arquivo/pasta existe fisicamente, NÃO reescreve
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# 2) Permitir acesso direto aos arquivos PWA (ANTES do front controller)
RewriteRule ^sw\.(js|php)$ - [L]
RewriteRule ^pwa-manifest\.php$ - [L]

# 3) Front controller (só chega aqui se não for arquivo estático)
RewriteRule ^ index.php [L]
```

### Cenário 3: Manifest retorna HTML em vez de JSON

**Possíveis causas:**
1. Arquivo passa pelo front controller e retorna página de login
2. Há output antes do JSON (warnings, BOM, espaços)
3. Arquivo não existe e retorna 404 estilizado

**Solução:**
- Garantir que `.htaccess` permite acesso direto (cenário 2)
- Verificar se arquivo existe fisicamente (cenário 1)
- Verificar código PHP do manifest (já corrigido)

---

## 📋 Checklist de Verificação

Execute na ordem:

- [ ] **A1:** `curl -i https://painel.cfcbomconselho.com.br/sw.js` retorna 200?
- [ ] **A2:** `curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php` retorna 200?
- [ ] **A3:** Body do manifest começa com `{`?
- [ ] **B1:** DocumentRoot identificado no painel Hostinger?
- [ ] **B2:** Arquivos existem fisicamente no DocumentRoot?
- [ ] **C1:** `.htaccess` permite acesso direto aos arquivos PWA?
- [ ] **C2:** Regra "arquivo existe" está antes do front controller?

---

## 🚀 Próximos Passos

1. **Execute o script de diagnóstico:**
   ```
   https://painel.cfcbomconselho.com.br/tools/diagnostico-pwa-acessibilidade.php
   ```

2. **Cole aqui os resultados dos comandos `curl -i`** para análise detalhada

3. **Confirme o DocumentRoot** do subdomínio `painel` no painel Hostinger

4. **Execute a solução** baseada no cenário identificado

---

## 📝 Nota Importante

**Por que o código JavaScript não resolve:**
- O `pwa_asset_path()` gera paths corretos (`/sw.js`)
- Mas o servidor web procura o arquivo **fisicamente** na raiz do DocumentRoot
- Se o arquivo não existe fisicamente, retorna 404
- Não há como o PHP "criar" o arquivo na raiz - ele precisa existir fisicamente

**Regra do PWA:**
- Manifest e Service Worker DEVEM estar acessíveis na raiz do escopo
- Não podem estar em subdiretórios
- Não podem passar por redirects
- Devem retornar 200 OK diretamente
