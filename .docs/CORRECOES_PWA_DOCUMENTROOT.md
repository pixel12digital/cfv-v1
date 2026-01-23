# 🔧 Correções PWA - DocumentRoot e Manifest

## ✅ Problemas Identificados e Corrigidos

### 1. Service Worker 404 - Arquivos no lugar errado ✅

**Problema:** 
- DocumentRoot é `/home/u502697186/domains/cfcbomconselho.com.br/public_html/painel`
- Arquivos `sw.js` e `sw.php` estavam em `public_html/sw.js` (subpasta)
- Navegador pede `/sw.js` na raiz → 404

**Solução:**
- ✅ Copiados `sw.js` e `sw.php` para a raiz do DocumentRoot (`painel/`)
- ✅ Arquivos agora estão acessíveis em `https://painel.cfcbomconselho.com.br/sw.js`

### 2. .htaccess não permitia acesso direto aos arquivos PWA ✅

**Problema:**
- `.htaccess` da raiz tinha front controller que capturava tudo
- Não havia exceções para `sw.js`, `sw.php`, `pwa-manifest.php` antes do rewrite

**Solução:**
- ✅ Adicionadas exceções no `.htaccess` da raiz ANTES do front controller:
  ```apache
  # Permitir acesso direto ao pwa-manifest.php
  RewriteRule ^pwa-manifest\.php$ - [L]
  
  # Permitir acesso direto ao sw.js e sw.php
  RewriteRule ^sw\.(js|php)$ - [L]
  
  # Permitir acesso direto aos assets
  RewriteRule ^assets/ - [L]
  
  # Se arquivo existe fisicamente, servir diretamente
  RewriteCond %{REQUEST_FILENAME} -f [OR]
  RewriteCond %{REQUEST_FILENAME} -d
  RewriteRule ^ - [L]
  ```

### 3. Manifest retornava erro SQL em vez de JSON ✅

**Problema:**
- `pwa-manifest.php` tentava conectar ao banco via `Cfc` model
- Em produção, DB retornava erro: `Access denied for user 'root'@'localhost'`
- Manifest retornava texto de erro em vez de JSON válido

**Solução:**
- ✅ Adicionado tratamento robusto de erros de conexão ao banco
- ✅ Captura específica de `PDOException` para erros de DB
- ✅ Fallback automático para manifest estático se DB falhar
- ✅ Garantido que NUNCA retorna erro ao cliente, sempre JSON válido

### 4. Git pull travado por certificado untracked ✅

**Problema:**
- `certificados/certificado.p12` está untracked e impede `git pull`

**Solução:**
- ✅ Adicionado `certificados/*.p12` ao `.gitignore`
- ⚠️ **AÇÃO NECESSÁRIA NO SERVIDOR:** Remover arquivo do índice do git sem apagar do disco:
  ```bash
  git rm --cached certificados/certificado.p12
  git commit -m "Remove certificado do tracking (adicionado ao .gitignore)"
  git pull
  ```

## 📋 Estrutura Final Esperada

```
/home/u502697186/domains/cfcbomconselho.com.br/public_html/painel/  (DocumentRoot)
├── sw.js                    ← NOVO (copiado da public_html/)
├── sw.php                   ← NOVO (copiado da public_html/)
├── pwa-manifest.php         ← Existe (já estava na raiz)
├── .htaccess                ← ATUALIZADO (exceções PWA adicionadas)
├── index.php                ← Front controller
└── public_html/             ← Subpasta (não é DocumentRoot)
    ├── sw.js                ← Mantido (backup)
    ├── sw.php               ← Mantido (backup)
    └── pwa-manifest.php     ← Mantido (versão atualizada)
```

## ✅ Testes Obrigatórios Após Deploy

Execute no servidor via SSH:

```bash
# 1. Verificar que arquivos estão na raiz
ls -lah /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel/sw.js
ls -lah /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel/sw.php

# 2. Testar HTTP - sw.js (deve retornar 200 + JavaScript)
curl -i https://painel.cfcbomconselho.com.br/sw.js | head -20

# 3. Testar HTTP - sw.php (deve retornar 200 + JavaScript)
curl -i https://painel.cfcbomconselho.com.br/sw.php | head -20

# 4. Testar HTTP - pwa-manifest.php (deve retornar 200 + JSON começando com {)
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -30

# 5. Verificar que o body do manifest começa com { (não "Erro...")
curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 50
```

## 🎯 Resultado Esperado

Após essas correções:

1. ✅ `/sw.js` retorna 200 + Content-Type: application/javascript
2. ✅ `/sw.php` retorna 200 + Content-Type: application/javascript  
3. ✅ `/pwa-manifest.php` retorna 200 + JSON válido começando com `{`
4. ✅ DevTools → Application → Manifest: sem erros
5. ✅ DevTools → Application → Service Workers: registrado com sucesso
6. ✅ Botão "Instalar aplicativo" aparece no navegador

## ⚠️ Próximos Passos no Servidor

1. **Fazer git pull:**
   ```bash
   cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel
   git rm --cached certificados/certificado.p12
   git commit -m "Remove certificado do tracking"
   git pull
   ```

2. **Verificar que arquivos foram copiados:**
   ```bash
   ls -lah sw.js sw.php
   ```

3. **Testar HTTP responses** (comandos acima)

4. **Limpar cache do navegador** e testar instalação PWA
