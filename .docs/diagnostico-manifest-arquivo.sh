#!/bin/bash
# Script de diagnóstico: identificar qual arquivo está sendo servido para /pwa-manifest.php

echo "=== DIAGNÓSTICO: Qual arquivo está sendo servido? ==="
echo ""

# 1. Encontrar todos os arquivos pwa-manifest.php
echo "1. Localizando todos os arquivos pwa-manifest.php:"
find . -name "pwa-manifest.php" -maxdepth 4 -type f -exec ls -lah {} \; 2>/dev/null | while read line; do
    echo "   $line"
done

echo ""
echo "2. Verificando conteúdo HTTP retornado:"
echo "   Primeiras 5 linhas do body:"
curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php 2>&1 | head -n 5

echo ""
echo "3. Verificando headers HTTP:"
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php 2>&1 | head -n 20

echo ""
echo "4. Primeiro caractere do body (deve ser {):"
FIRST_CHAR=$(curl -s https://painel.cfcbomconselho.com.br/pwa-manifest.php 2>&1 | head -c 1)
echo "   Primeiro caractere: '$FIRST_CHAR'"
if [ "$FIRST_CHAR" = "{" ]; then
    echo "   ✅ CORRETO: Começa com {"
else
    echo "   ❌ ERRO: Não começa com { (é '$FIRST_CHAR')"
fi

echo ""
echo "5. Verificando se arquivo existe na raiz:"
if [ -f "pwa-manifest.php" ]; then
    echo "   ✅ pwa-manifest.php existe na raiz"
    echo "   Data de modificação:"
    ls -lah pwa-manifest.php | awk '{print "   "$6, $7, $8, $9}'
    echo "   Primeiras 3 linhas:"
    head -n 3 pwa-manifest.php | sed 's/^/   /'
else
    echo "   ❌ pwa-manifest.php NÃO existe na raiz"
fi

echo ""
echo "6. Verificando se arquivo existe em public_html/:"
if [ -f "public_html/pwa-manifest.php" ]; then
    echo "   ✅ public_html/pwa-manifest.php existe"
    echo "   Data de modificação:"
    ls -lah public_html/pwa-manifest.php | awk '{print "   "$6, $7, $8, $9}'
    echo "   Primeiras 3 linhas:"
    head -n 3 public_html/pwa-manifest.php | sed 's/^/   /'
else
    echo "   ❌ public_html/pwa-manifest.php NÃO existe"
fi

echo ""
echo "=== CONCLUSÃO ==="
echo "Se o primeiro caractere não é '{', o servidor está servindo um arquivo diferente."
echo "Copie o arquivo isolado para a raiz: cp public_html/pwa-manifest.php pwa-manifest.php"
