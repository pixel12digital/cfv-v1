# 🔍 Diagnóstico PWA - Coleta de Evidências

## ⚠️ IMPORTANTE: O que realmente importa

**Existência física do arquivo ≠ Response HTTP válido**

O erro "Manifest: Line 1, column 1" geralmente significa que o response HTTP começa com HTML (`<!doctype...>`) ou algum conteúdo antes do `{`, não que o arquivo não existe no disco.

---

## 📋 Checklist de Evidências Necessárias

### 1. DocumentRoot Real do Subdomínio `painel`

**Como verificar:**

**Opção A - Via Painel Hostinger:**
1. Acesse: **Domínios** → **Subdomínios** → `painel`
2. Veja o campo **"Raiz do Site"** ou **"DocumentRoot"**
3. Anote o caminho completo (ex: `/public_html/` ou `/public_html/painel/public_html/`)

**Opção B - Via SSH:**
```bash
# Verificar configuração do Apache/VirtualHost
grep -r "painel.cfcbomconselho.com.br" /etc/apache2/sites-enabled/ 2>/dev/null
# ou
httpd -S 2>/dev/null | grep painel
```

**Opção C - Via PHP no servidor:**
Crie um arquivo `info.php` no DocumentRoot e acesse:
```php
<?php
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "__DIR__: " . __DIR__ . "\n";
?>
```

---

### 2. Existência Física no DocumentRoot

**Execute no servidor (SSH):**

```bash
# Substitua /caminho/do/DocumentRoot pelo valor encontrado acima
DOCUMENT_ROOT="/caminho/do/DocumentRoot"

# Verificar existência
ls -lah $DOCUMENT_ROOT/sw.js
ls -lah $DOCUMENT_ROOT/sw.php
ls -lah $DOCUMENT_ROOT/pwa-manifest.php

# Verificar permissões
stat $DOCUMENT_ROOT/sw.js
stat $DOCUMENT_ROOT/pwa-manifest.php
```

**O que esperar:**
- ✅ Arquivos devem existir e ter permissões 644 ou 755
- ✅ Diretório deve ter permissão 755

---

### 3. Response HTTP Real (CRÍTICO)

**Execute localmente ou no servidor:**

```bash
# Testar sw.js
curl -i https://painel.cfcbomconselho.com.br/sw.js

# Testar sw.php
curl -i https://painel.cfcbomconselho.com.br/sw.php

# Testar pwa-manifest.php
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php
```

**O que verificar em cada response:**

#### Para `sw.js` e `sw.php`:
- ✅ **Status:** `200 OK`
- ✅ **Content-Type:** `application/javascript` ou `text/javascript`
- ✅ **Body:** Deve começar com `//` (comentário JavaScript) ou código JavaScript válido
- ❌ **Se vier HTML:** Cole as primeiras ~200 caracteres do body

#### Para `pwa-manifest.php`:
- ✅ **Status:** `200 OK`
- ✅ **Content-Type:** `application/manifest+json` ou `application/json`
- ✅ **Body:** Deve começar com `{` (JSON válido)
- ❌ **Se vier HTML:** Cole as primeiras ~200 caracteres do body
- ❌ **Se vier redirect:** Verifique o header `Location:`

---

### 4. Verificar Rewrite/Front Controller

**Se os arquivos existem mas retornam HTML/404:**

Verifique o `.htaccess` no DocumentRoot:

```bash
cat $DOCUMENT_ROOT/.htaccess
```

**O que procurar:**

✅ **Deve ter ANTES do front controller:**
```apache
# Permitir arquivos estáticos
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Permitir acesso direto aos arquivos PWA
RewriteRule ^sw\.(js|php)$ - [L]
RewriteRule ^pwa-manifest\.php$ - [L]

# Front controller (só chega aqui se não for arquivo estático)
RewriteRule ^ index.php [L]
```

❌ **Problema comum:**
```apache
# ERRADO: Front controller ANTES das regras de arquivo estático
RewriteRule ^ index.php [L]  # ← Isso captura TUDO, incluindo sw.js
```

---

## 🎯 Interpretação dos Resultados

### Cenário 1: Arquivos não existem no DocumentRoot
**Sintoma:** `ls -lah` mostra que arquivos não existem  
**Solução:** Copiar arquivos para o DocumentRoot correto

### Cenário 2: Arquivos existem mas retornam 404
**Sintoma:** `ls -lah` mostra arquivos, mas `curl` retorna 404  
**Causa:** Rewrite/Front Controller interceptando  
**Solução:** Ajustar `.htaccess` para permitir acesso direto ANTES do front controller

### Cenário 3: Arquivos retornam HTML (login/redirect)
**Sintoma:** `curl` retorna 200 mas body começa com `<!doctype` ou `<html`  
**Causa:** Front Controller processando e retornando página de login  
**Solução:** Ajustar `.htaccess` ou verificar se arquivo está sendo processado pelo PHP incorretamente

### Cenário 4: Manifest retorna HTML em vez de JSON
**Sintoma:** `curl` retorna 200 mas Content-Type é `text/html` e body começa com `<`  
**Causa:** 
- Arquivo passando pelo front controller
- Output antes do JSON (warnings, BOM, espaços)
- Redirect para página de login
**Solução:** 
- Garantir que `.htaccess` permite acesso direto
- Verificar código PHP do manifest (já corrigido)

---

## 📝 Template para Resposta

Cole aqui os resultados:

```
=== 1. DocumentRoot ===
[Caminho do DocumentRoot do subdomínio painel]

=== 2. Existência Física ===
[Output do ls -lah dos arquivos]

=== 3. Response HTTP ===
[Output completo do curl -i para cada arquivo]

=== 4. .htaccess ===
[Conteúdo do .htaccess no DocumentRoot]
```

Com essas evidências, posso identificar exatamente qual camada está quebrando e fornecer a solução específica.
