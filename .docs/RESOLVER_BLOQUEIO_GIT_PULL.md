# 🔧 Resolver Bloqueio Git Pull e Corrigir PWA

## ⚠️ Problema Atual

O `git pull` está abortando por causa de:
1. Mudanças locais no `.htaccess` que conflitam com o repositório
2. Arquivo untracked `certificados/certificado.p12` que impede o merge

**NÃO tentar fazer commit no servidor** - não há identidade Git configurada.

## ✅ Solução: Limpar Bloqueio SEM Commit

Execute **EXATAMENTE NESTA ORDEM** no servidor via SSH:

```bash
cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel

# PASSO 1: Verificar status atual
git status

# PASSO 2: Stash das mudanças locais no .htaccess (salvar temporariamente)
git stash push -m "Salvar .htaccess local antes do pull" .htaccess

# PASSO 3: Mover certificado (é untracked, não precisa rm --cached)
mv certificados/certificado.p12 certificados/certificado.p12.backup

# PASSO 4: Tentar pull novamente
git pull

# PASSO 5: Verificar que funcionou - hash deve ser f36ed78 ou mais recente
git rev-parse --short HEAD
```

**Se o `git pull` ainda falhar**, verificar:

```bash
# Ver status completo
git status

# Se ainda houver conflitos, descartar mudanças locais e usar versão do repositório
git checkout --theirs .htaccess
git add .htaccess
git pull
```

---

## ✅ Após Git Pull Funcionar: Garantir Arquivos PWA na Raiz

```bash
# Verificar se sw.js e sw.php estão na raiz do DocumentRoot
ls -lah sw.js sw.php

# Se não existirem, copiar de public_html/
if [ ! -f "sw.js" ]; then
    cp public_html/sw.js sw.js
    echo "sw.js copiado para raiz"
fi

if [ ! -f "sw.php" ]; then
    cp public_html/sw.php sw.php
    echo "sw.php copiado para raiz"
fi

# Verificar permissões
chmod 644 sw.js sw.php
```

---

## ✅ Testar HTTP Responses

```bash
# 1. Testar sw.js (deve retornar 200 + JavaScript)
echo "=== TESTE sw.js ==="
curl -i https://painel.cfcbomconselho.com.br/sw.js 2>&1 | head -20

# 2. Testar sw.php (deve retornar 200 + JavaScript)
echo ""
echo "=== TESTE sw.php ==="
curl -i https://painel.cfcbomconselho.com.br/sw.php 2>&1 | head -20

# 3. Testar pwa-manifest.php (deve retornar 200 + JSON começando com {)
echo ""
echo "=== TESTE pwa-manifest.php ==="
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php 2>&1 | head -30

# 4. VERIFICAÇÃO CRÍTICA: Primeiros caracteres do manifest (deve ser {)
echo ""
echo "=== VERIFICAÇÃO: Primeiros 50 caracteres do manifest ==="
curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 50
echo ""
```

**Resultado esperado:**
- ✅ `/sw.js` → HTTP 200 + `Content-Type: application/javascript`
- ✅ `/sw.php` → HTTP 200 + `Content-Type: application/javascript`
- ✅ `/pwa-manifest.php` → HTTP 200 + body começando com `{` (NÃO "Erro...")

---

## ⚠️ Se Manifest Ainda Retornar "Erro na conexão"

Isso significa que o arquivo `pwa-manifest.php` no servidor ainda não foi atualizado. Verificar:

```bash
# Verificar data de modificação
ls -lah public_html/pwa-manifest.php

# Verificar se o arquivo tem o tratamento de PDOException (deve existir)
grep -n "PDOException" public_html/pwa-manifest.php

# Se não encontrar, verificar se o git pull trouxe a versão correta
git log --oneline -5
git show HEAD:public_html/pwa-manifest.php | grep -A 3 "PDOException"
```

Se o arquivo não tiver `PDOException`, significa que o git pull não atualizou o arquivo. Tentar:

```bash
# Forçar checkout do arquivo do repositório
git checkout HEAD -- public_html/pwa-manifest.php

# Verificar novamente
grep -n "PDOException" public_html/pwa-manifest.php
```

---

## 🎯 Checklist Final

- [ ] `git pull` completou sem erros
- [ ] `git rev-parse --short HEAD` mostra `f36ed78` ou mais recente
- [ ] `sw.js` existe na raiz (`ls -lah sw.js` mostra o arquivo)
- [ ] `sw.php` existe na raiz (`ls -lah sw.php` mostra o arquivo)
- [ ] `curl -i https://painel.cfcbomconselho.com.br/sw.js` retorna 200
- [ ] `curl -i https://painel.cfcbomconselho.com.br/sw.php` retorna 200
- [ ] `curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 1` retorna `{`

---

## 📝 Script Automatizado

Um script bash está disponível em `.docs/resolver-git-pull-e-pwa.sh` que automatiza todos esses passos. Para usar:

```bash
cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel
chmod +x .docs/resolver-git-pull-e-pwa.sh
.docs/resolver-git-pull-e-pwa.sh
```

Ou copiar o conteúdo do script e executar diretamente no servidor.
