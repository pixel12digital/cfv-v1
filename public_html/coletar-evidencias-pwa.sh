#!/bin/bash
# Script de Coleta de Evidências PWA - Execute no servidor via SSH
# Execute este script no diretório onde você está (painel)

echo "=========================================="
echo "COLETA DE EVIDÊNCIAS PWA"
echo "=========================================="
echo ""

# 1. Identificar DocumentRoot
echo "=== A) IDENTIFICAR DOCUMENTROOT ==="
echo ""
echo "Diretório atual:"
pwd
echo ""

# Tentar identificar via PHP se disponível
if command -v php &> /dev/null; then
    echo "Tentando identificar DocumentRoot via PHP:"
    php -r "echo 'DOCUMENT_ROOT: ' . (\$_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . PHP_EOL;" 2>/dev/null || echo "Não foi possível executar PHP"
    echo ""
fi

# Verificar estrutura atual
echo "Estrutura do diretório atual:"
ls -lah | head -20
echo ""

# 2. Verificar existência física dos arquivos
echo "=== B) VERIFICAR EXISTÊNCIA FÍSICA ==="
echo ""

# Tentar encontrar arquivos PWA no diretório atual e subdiretórios
echo "Procurando arquivos PWA..."
echo ""

# Verificar no diretório atual
CURRENT_DIR=$(pwd)
FILES_FOUND=0

for file in sw.js sw.php pwa-manifest.php; do
    # Tentar no diretório atual
    if [ -f "$CURRENT_DIR/$file" ]; then
        echo "✅ $file encontrado em: $CURRENT_DIR/$file"
        ls -lah "$CURRENT_DIR/$file"
        FILES_FOUND=$((FILES_FOUND + 1))
    # Tentar em public_html/ se existir
    elif [ -f "$CURRENT_DIR/public_html/$file" ]; then
        echo "✅ $file encontrado em: $CURRENT_DIR/public_html/$file"
        ls -lah "$CURRENT_DIR/public_html/$file"
        FILES_FOUND=$((FILES_FOUND + 1))
    else
        echo "❌ $file NÃO encontrado em:"
        echo "   - $CURRENT_DIR/$file"
        echo "   - $CURRENT_DIR/public_html/$file"
    fi
    echo ""
done

# 3. Testar response HTTP
echo "=== C) TESTAR RESPONSE HTTP (curl) ==="
echo ""
echo "Testando acesso público aos arquivos..."
echo ""

DOMAIN="painel.cfcbomconselho.com.br"

for file in sw.js sw.php pwa-manifest.php; do
    URL="https://$DOMAIN/$file"
    echo "----------------------------------------"
    echo "Testando: $URL"
    echo "----------------------------------------"
    curl -i "$URL" 2>&1 | head -30
    echo ""
    echo ""
done

# 4. Verificar .htaccess
echo "=== D) VERIFICAR .htaccess ==="
echo ""

# Procurar .htaccess no diretório atual e public_html
HTACCESS_FOUND=0

for htaccess_path in "$CURRENT_DIR/.htaccess" "$CURRENT_DIR/public_html/.htaccess"; do
    if [ -f "$htaccess_path" ]; then
        echo "Arquivo .htaccess encontrado em: $htaccess_path"
        echo "---"
        cat "$htaccess_path"
        echo ""
        HTACCESS_FOUND=$((HTACCESS_FOUND + 1))
    fi
done

if [ $HTACCESS_FOUND -eq 0 ]; then
    echo "⚠️ Nenhum arquivo .htaccess encontrado em:"
    echo "   - $CURRENT_DIR/.htaccess"
    echo "   - $CURRENT_DIR/public_html/.htaccess"
fi

echo ""
echo "=========================================="
echo "RESUMO"
echo "=========================================="
echo ""
echo "Arquivos PWA encontrados: $FILES_FOUND/3"
echo "Arquivos .htaccess encontrados: $HTACCESS_FOUND"
echo ""
echo "Próximos passos:"
echo "1. Se arquivos não foram encontrados, identifique o DocumentRoot correto"
echo "2. Copie os arquivos para o DocumentRoot identificado"
echo "3. Verifique se os curl retornam 200 OK e Content-Type correto"
echo "4. Se retornarem HTML, verifique o .htaccess e regras de rewrite"
