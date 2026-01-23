#!/bin/bash
# Script para resolver bloqueio do git pull e garantir arquivos PWA no lugar correto

set -e  # Parar em caso de erro

echo "=== PASSO 1: Limpar bloqueio do git pull ==="
cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel

# Verificar status atual
echo ""
echo "Status atual do git:"
git status --short

# Stash das mudanças locais no .htaccess (se houver)
if git diff --quiet .htaccess 2>/dev/null; then
    echo ".htaccess não tem mudanças locais"
else
    echo "Salvando mudanças locais do .htaccess em stash..."
    git stash push -m "Salvar .htaccess local antes do pull" .htaccess || true
fi

# Mover certificado para fora do caminho (é untracked, não precisa rm --cached)
if [ -f "certificados/certificado.p12" ]; then
    echo "Movendo certificado para backup..."
    mv certificados/certificado.p12 certificados/certificado.p12.backup || true
fi

# Tentar pull
echo ""
echo "Executando git pull..."
git pull || {
    echo "ERRO: git pull falhou. Verificando status..."
    git status
    exit 1
}

# Verificar que o pull funcionou
echo ""
echo "Hash do commit atual:"
git rev-parse --short HEAD

echo ""
echo "=== PASSO 2: Verificar/Copiar arquivos PWA para raiz ==="

# Verificar se sw.js existe na raiz
if [ ! -f "sw.js" ]; then
    if [ -f "public_html/sw.js" ]; then
        echo "Copiando sw.js para raiz..."
        cp public_html/sw.js sw.js
    else
        echo "ERRO: sw.js não encontrado nem na raiz nem em public_html/"
        exit 1
    fi
else
    echo "sw.js já existe na raiz"
fi

# Verificar se sw.php existe na raiz
if [ ! -f "sw.php" ]; then
    if [ -f "public_html/sw.php" ]; then
        echo "Copiando sw.php para raiz..."
        cp public_html/sw.php sw.php
    else
        echo "ERRO: sw.php não encontrado nem na raiz nem em public_html/"
        exit 1
    fi
else
    echo "sw.php já existe na raiz"
fi

# Verificar permissões
chmod 644 sw.js sw.php 2>/dev/null || true

echo ""
echo "=== PASSO 3: Verificar arquivos ==="
ls -lah sw.js sw.php

echo ""
echo "=== PASSO 4: Testar HTTP responses ==="

echo ""
echo "Testando sw.js:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" https://painel.cfcbomconselho.com.br/sw.js || echo "ERRO ao testar sw.js"

echo ""
echo "Testando sw.php:"
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" https://painel.cfcbomconselho.com.br/sw.php || echo "ERRO ao testar sw.php"

echo ""
echo "Testando pwa-manifest.php (primeiros 50 caracteres):"
curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php | head -c 50
echo ""

echo ""
echo "=== CONCLUSÃO ==="
echo "Se todos os testes retornaram 200 e o manifest começa com '{', o PWA deve estar funcionando."
echo "Caso contrário, verifique os logs acima para identificar o problema."
