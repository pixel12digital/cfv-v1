# 🔧 Correção Estrutural PWA - Produção

## ❌ Problema Identificado

**Situação atual em produção:**
- DocumentRoot: `/public_html/`
- Arquivos PWA estão em: `/public_html/painel/public_html/`
- Resultado: 404 porque o servidor não encontra os arquivos na raiz

**URLs que o navegador tenta acessar:**
- `https://painel.cfcbomconselho.com.br/sw.js` → procura em `/public_html/sw.js` ❌ (não existe)
- `https://painel.cfcbomconselho.com.br/pwa-manifest.php` → procura em `/public_html/pwa-manifest.php` ❌ (não existe)

**Onde os arquivos realmente estão:**
- `/public_html/painel/public_html/sw.js` ✅ (existe, mas inacessível)
- `/public_html/painel/public_html/pwa-manifest.php` ✅ (existe, mas inacessível)

## ✅ Solução

**Os arquivos PWA precisam estar na raiz do DocumentRoot:**

```
/public_html/
├── sw.js                    ← DEVE ESTAR AQUI
├── pwa-manifest.php         ← DEVE ESTAR AQUI
└── icons/
    ├── icon-192x192.png     ← DEVE ESTAR AQUI
    └── icon-512x512.png     ← DEVE ESTAR AQUI
```

## 📋 Ação Necessária em Produção

### Opção 1: Copiar arquivos para a raiz (Recomendado)

Via SSH, executar:

```bash
# Copiar arquivos PWA para a raiz do DocumentRoot
cp /public_html/painel/public_html/sw.js /public_html/sw.js
cp /public_html/painel/public_html/pwa-manifest.php /public_html/pwa-manifest.php

# Copiar ícones (se necessário)
cp -r /public_html/painel/public_html/icons /public_html/icons
```

### Opção 2: Criar symlinks (Alternativa)

```bash
# Criar symlinks na raiz apontando para os arquivos reais
ln -s /public_html/painel/public_html/sw.js /public_html/sw.js
ln -s /public_html/painel/public_html/pwa-manifest.php /public_html/pwa-manifest.php
ln -s /public_html/painel/public_html/icons /public_html/icons
```

### Opção 3: Ajustar DocumentRoot (Se possível)

Se tiver acesso à configuração do Apache/Nginx, alterar DocumentRoot para:
```
DocumentRoot /public_html/painel/public_html/
```

## ✅ Verificação

Após aplicar a solução, verificar:

1. **Acesso direto aos arquivos:**
   - `https://painel.cfcbomconselho.com.br/sw.js` → deve retornar 200 OK
   - `https://painel.cfcbomconselho.com.br/pwa-manifest.php` → deve retornar 200 OK

2. **DevTools → Application:**
   - Manifest: deve carregar sem erros
   - Service Workers: deve registrar corretamente

3. **Console:**
   - Não deve ter mais 404 para `sw.js` e `pwa-manifest.php`

4. **Botão "Instalar Aplicativo":**
   - Deve aparecer no menu do usuário quando `beforeinstallprompt` disparar

## 📝 Nota Técnica

**Por que o código PHP não resolve:**
- O `pwa_asset_path()` gera paths corretos (`/sw.js`)
- Mas o servidor web (Apache/Nginx) procura o arquivo físico na raiz do DocumentRoot
- Se o arquivo não existe fisicamente na raiz, retorna 404
- Não há como o PHP "criar" o arquivo na raiz - ele precisa existir fisicamente

**Regra do PWA:**
- Manifest e Service Worker DEVEM estar acessíveis na raiz do escopo
- Não podem estar em subdiretórios
- Não podem passar por redirects
- Devem retornar 200 OK diretamente

## ✅ Status Local

**Localmente está correto:**
- ✅ `public_html/sw.js` existe
- ✅ `public_html/pwa-manifest.php` existe
- ✅ `public_html/icons/` existe

**Apenas produção precisa de ajuste estrutural.**
