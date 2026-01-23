# 🔧 Resolver Bloqueio Git Pull e Corrigir Manifest PWA

## ⚠️ Problema Identificado

1. **Git pull está travando** porque existe `pwa-manifest.php` untracked na raiz (versão antiga de Jan 22)
2. **Arquivo antigo na raiz** está sendo servido e retorna erro SQL: `Erro na conexão: SQLSTATE[HY000] [1045] Access denied`
3. **Arquivo novo isolado** não foi baixado porque o pull foi abortado

## ✅ Solução: Remover Arquivo Antigo e Fazer Pull

Execute **EXATAMENTE NESTA ORDEM** no servidor via SSH:

```bash
cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel

# PASSO 1: Verificar arquivo atual na raiz (deve ser versão antiga)
ls -lah pwa-manifest.php
head -n 5 pwa-manifest.php

# PASSO 2: Remover arquivo antigo da raiz (liberar para git pull)
rm pwa-manifest.php

# PASSO 3: Verificar que foi removido
ls -lah pwa-manifest.php 2>&1 || echo "Arquivo removido com sucesso"

# PASSO 4: Fazer pull agora (deve funcionar)
git pull

# PASSO 5: Verificar que arquivo novo foi baixado
ls -lah pwa-manifest.php
head -n 5 pwa-manifest.php

# Deve mostrar: "Manifest PWA - Versão Isolada" (não deve ter código de DB)
```

## ✅ Validação: Testar HTTP Response

Após o pull, validar que a URL está servindo o arquivo correto:

```bash
# TESTE CRÍTICO 1: Primeiro caractere deve ser {
FIRST_CHAR=$(curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 1)
echo "Primeiro caractere: '$FIRST_CHAR'"
if [ "$FIRST_CHAR" = "{" ]; then
    echo "✅ CORRETO: Começa com {"
else
    echo "❌ ERRO: Não começa com { (é '$FIRST_CHAR')"
fi

# TESTE CRÍTICO 2: Não deve conter "SQLSTATE" ou "Access denied"
BODY=$(curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -n 3)
if echo "$BODY" | grep -q "SQLSTATE\|Access denied\|Erro na conexão"; then
    echo "❌ ERRO: Body ainda contém erro de DB"
    echo "Conteúdo:"
    echo "$BODY"
else
    echo "✅ CORRETO: Body não contém erros de DB"
    echo "Primeiras linhas:"
    echo "$BODY"
fi

# TESTE CRÍTICO 3: Deve ser JSON válido
curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 50
echo ""
```

## 🎯 Resultado Esperado

Após seguir os passos acima:

- ✅ `git pull` completa sem erros
- ✅ `pwa-manifest.php` na raiz é a versão isolada (sem código de DB)
- ✅ `curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 1` retorna `{`
- ✅ Body não contém "SQLSTATE", "Access denied" ou "Erro na conexão"
- ✅ DevTools → Application → Manifest: sem erros
- ✅ Console não mostra: "Manifest: Line 1, column 1, Syntax error"

## ⚠️ Se Ainda Houver Problemas

### Se git pull ainda falhar:

```bash
# Verificar status completo
git status

# Se houver outros arquivos untracked bloqueando, removê-los ou movê-los
# Exemplo: certificados/certificado.p12
mv certificados/certificado.p12 certificados/certificado.p12.backup

# Tentar pull novamente
git pull
```

### Se o arquivo ainda retornar erro SQL:

```bash
# Verificar qual arquivo está sendo servido
# Comparar conteúdo do arquivo na raiz com o esperado
head -n 20 pwa-manifest.php | grep -i "isolado\|database\|bootstrap"

# Se não encontrar "isolado", o arquivo não foi atualizado
# Forçar checkout do arquivo do repositório
git checkout HEAD -- pwa-manifest.php

# Verificar novamente
head -n 5 pwa-manifest.php
```

### Se houver cache:

```bash
# Testar com querystring para bypassar cache
curl -s "https://painel.cfcbomconselho.com.br/pwa-manifest.php?v=$(date +%s)" | head -c 1

# Se retornar { com querystring mas não sem, há cache
# Aguardar alguns minutos ou limpar cache do servidor
```

## 📋 Checklist Final

- [ ] Arquivo antigo `pwa-manifest.php` foi removido da raiz
- [ ] `git pull` completou sem erros
- [ ] Arquivo novo `pwa-manifest.php` existe na raiz
- [ ] Arquivo novo contém "Versão Isolada" (sem código de DB)
- [ ] `curl -s .../pwa-manifest.php | head -c 1` retorna `{`
- [ ] Body não contém "SQLSTATE" ou "Access denied"
- [ ] DevTools → Application → Manifest: sem erros
- [ ] Console não mostra erro de sintaxe do manifest
