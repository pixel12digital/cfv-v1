#!/bin/bash
# Diagnóstico PWA - Execução no Servidor
# Execute este script via SSH no servidor de produção

echo "=========================================="
echo "DIAGNÓSTICO PWA - SERVIDOR"
echo "=========================================="
echo ""

# 1. Identificar DocumentRoot
echo "=== 1. DOCUMENTROOT ==="
if [ -n "$DOCUMENT_ROOT" ]; then
    echo "DOCUMENT_ROOT (variável): $DOCUMENT_ROOT"
else
    echo "DOCUMENT_ROOT não definido na variável de ambiente"
fi

# Tentar identificar via PHP
PHP_INFO=$(php -r "echo \$_SERVER['DOCUMENT_ROOT'] ?? 'N/A';" 2>/dev/null)
if [ "$PHP_INFO" != "N/A" ]; then
    echo "DOCUMENT_ROOT (via PHP): $PHP_INFO"
    DOC_ROOT="$PHP_INFO"
else
    # Tentar caminhos comuns
    if [ -d "/home/*/public_html" ]; then
        DOC_ROOT=$(find /home -name "public_html" -type d 2>/dev/null | head -1)
        echo "DOCUMENT_ROOT (detectado): $DOC_ROOT"
    else
        echo "⚠️ Não foi possível detectar DocumentRoot automaticamente"
        echo "Por favor, informe manualmente:"
        read -p "DocumentRoot: " DOC_ROOT
    fi
fi

echo ""

# 2. Verificar existência física
echo "=== 2. EXISTÊNCIA FÍSICA ==="
if [ -z "$DOC_ROOT" ]; then
    echo "❌ DocumentRoot não identificado. Execute manualmente:"
    echo "   ls -lah /caminho/do/DocumentRoot/sw.js"
    echo "   ls -lah /caminho/do/DocumentRoot/pwa-manifest.php"
else
    echo "Verificando em: $DOC_ROOT"
    echo ""
    
    for file in sw.js sw.php pwa-manifest.php; do
        FILE_PATH="$DOC_ROOT/$file"
        if [ -f "$FILE_PATH" ]; then
            SIZE=$(stat -f%z "$FILE_PATH" 2>/dev/null || stat -c%s "$FILE_PATH" 2>/dev/null)
            PERMS=$(stat -f%Sp "$FILE_PATH" 2>/dev/null || stat -c%a "$FILE_PATH" 2>/dev/null)
            echo "✅ $file existe"
            echo "   Caminho: $FILE_PATH"
            echo "   Tamanho: $SIZE bytes"
            echo "   Permissões: $PERMS"
        else
            echo "❌ $file NÃO existe em $FILE_PATH"
        fi
        echo ""
    done
fi

echo ""

# 3. Testar acesso HTTP
echo "=== 3. TESTE DE ACESSO HTTP ==="
echo "Testando acesso público aos arquivos..."
echo ""

DOMAIN="painel.cfcbomconselho.com.br"

for file in sw.js sw.php pwa-manifest.php; do
    URL="https://$DOMAIN/$file"
    echo "Testando: $URL"
    echo "---"
    
    RESPONSE=$(curl -s -i -w "\n%{http_code}" "$URL" 2>&1)
    HTTP_CODE=$(echo "$RESPONSE" | tail -1)
    HEADERS=$(echo "$RESPONSE" | sed '$d')
    BODY=$(curl -s "$URL" 2>&1 | head -c 200)
    
    echo "Status HTTP: $HTTP_CODE"
    
    if [ "$HTTP_CODE" = "200" ]; then
        echo "✅ Status OK"
        
        # Verificar Content-Type
        CONTENT_TYPE=$(echo "$HEADERS" | grep -i "content-type" | head -1)
        echo "$CONTENT_TYPE"
        
        # Verificar primeiro caractere do body
        FIRST_CHAR=$(echo "$BODY" | head -c 1)
        echo "Primeiro caractere do body: '$FIRST_CHAR'"
        
        if [ "$file" = "pwa-manifest.php" ]; then
            if [ "$FIRST_CHAR" = "{" ]; then
                echo "✅ JSON válido (começa com {)"
            elif [ "$FIRST_CHAR" = "<" ]; then
                echo "❌ ERRO: Retornando HTML em vez de JSON"
                echo "Primeiros 200 caracteres:"
                echo "$BODY"
            else
                echo "⚠️ AVISO: Não começa com { ou <"
                echo "Primeiros 200 caracteres:"
                echo "$BODY"
            fi
        fi
    else
        echo "❌ Erro HTTP: $HTTP_CODE"
        if [ -n "$BODY" ]; then
            echo "Body (primeiros 200 caracteres):"
            echo "$BODY"
        fi
    fi
    
    echo ""
done

echo ""

# 4. Verificar .htaccess
echo "=== 4. VERIFICAÇÃO DO .htaccess ==="
if [ -n "$DOC_ROOT" ] && [ -f "$DOC_ROOT/.htaccess" ]; then
    echo "Arquivo .htaccess encontrado em: $DOC_ROOT/.htaccess"
    echo ""
    echo "Verificando regras relevantes:"
    echo "---"
    
    # Verificar se tem regra para arquivos estáticos
    if grep -q "REQUEST_FILENAME.*-f" "$DOC_ROOT/.htaccess"; then
        echo "✅ Regra para arquivos estáticos encontrada"
    else
        echo "⚠️ Regra para arquivos estáticos NÃO encontrada"
    fi
    
    # Verificar se tem regra para sw.js/sw.php
    if grep -q "sw\.\(js\|php\)" "$DOC_ROOT/.htaccess"; then
        echo "✅ Regra para sw.js/sw.php encontrada"
    else
        echo "⚠️ Regra para sw.js/sw.php NÃO encontrada"
    fi
    
    # Verificar se tem regra para pwa-manifest.php
    if grep -q "pwa-manifest\.php" "$DOC_ROOT/.htaccess"; then
        echo "✅ Regra para pwa-manifest.php encontrada"
    else
        echo "⚠️ Regra para pwa-manifest.php NÃO encontrada"
    fi
    
    echo ""
    echo "Conteúdo completo do .htaccess:"
    echo "---"
    cat "$DOC_ROOT/.htaccess"
else
    echo "⚠️ Arquivo .htaccess não encontrado ou DocumentRoot não identificado"
fi

echo ""
echo "=========================================="
echo "FIM DO DIAGNÓSTICO"
echo "=========================================="
