<?php
/**
 * Diagnóstico PWA - Execução Local
 * 
 * Este script verifica a existência dos arquivos PWA localmente
 */

echo "=== DIAGNÓSTICO PWA - VERIFICAÇÃO LOCAL ===\n\n";

// Diretório atual
$dir = __DIR__;
echo "Diretório atual: $dir\n\n";

// Arquivos PWA para verificar
$files = [
    'sw.js' => $dir . '/sw.js',
    'sw.php' => $dir . '/sw.php',
    'pwa-manifest.php' => $dir . '/pwa-manifest.php',
];

echo "=== VERIFICAÇÃO DE ARQUIVOS ===\n\n";

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = $exists ? is_readable($path) : false;
    $size = $exists ? filesize($path) : 0;
    
    echo "Arquivo: $name\n";
    echo "  Caminho: $path\n";
    echo "  Existe: " . ($exists ? "✅ SIM" : "❌ NÃO") . "\n";
    
    if ($exists) {
        echo "  Tamanho: $size bytes\n";
        echo "  Legível: " . ($readable ? "✅ SIM" : "❌ NÃO") . "\n";
        
        // Verificar conteúdo do manifest
        if ($name == 'pwa-manifest.php') {
            $content = file_get_contents($path);
            $firstChar = substr(trim($content), 0, 1);
            echo "  Primeiro caractere: '$firstChar'\n";
            
            if ($firstChar == '{') {
                echo "  ✅ JSON válido (começa com {)\n";
            } elseif ($firstChar == '<') {
                echo "  ❌ ERRO: Parece ser HTML (começa com <)\n";
            } else {
                echo "  ⚠️ AVISO: Não começa com { ou <\n";
            }
        }
        
        // Verificar conteúdo do sw.js
        if ($name == 'sw.js') {
            $content = file_get_contents($path);
            if (strpos($content, 'Service Worker') !== false) {
                echo "  ✅ Parece ser um Service Worker válido\n";
            } else {
                echo "  ⚠️ AVISO: Conteúdo não parece ser um Service Worker\n";
            }
        }
    }
    echo "\n";
}

echo "=== INFORMAÇÕES DO SERVIDOR (simuladas) ===\n\n";
echo "Para verificar em produção, execute:\n";
echo "  curl -i https://painel.cfcbomconselho.com.br/sw.js\n";
echo "  curl -i https://painel.cfcbomconselho.com.br/sw.php\n";
echo "  curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php\n\n";

echo "=== ESTRUTURA DE DIRETÓRIOS ===\n\n";
function listDir($dir, $maxDepth = 2, $currentDepth = 0, $prefix = '') {
    if ($currentDepth >= $maxDepth) return;
    if (!is_dir($dir)) return;
    
    $items = @scandir($dir);
    if (!$items) return;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        if (strpos($item, '.') === 0 && $item != '.htaccess') continue;
        
        $path = $dir . '/' . $item;
        $isDir = is_dir($path);
        
        echo $prefix . ($isDir ? '[DIR] ' : '[FILE] ') . $item . "\n";
        
        if ($isDir && $currentDepth < $maxDepth - 1) {
            listDir($path, $maxDepth, $currentDepth + 1, $prefix . '  ');
        }
    }
}

listDir($dir, 2);

echo "\n=== CONCLUSÃO ===\n\n";
$allExist = true;
foreach ($files as $name => $path) {
    if (!file_exists($path)) {
        $allExist = false;
        echo "❌ $name não encontrado em: $path\n";
    }
}

if ($allExist) {
    echo "✅ Todos os arquivos PWA existem localmente!\n";
    echo "\n⚠️ IMPORTANTE: Verifique se estes arquivos também existem no DocumentRoot de produção.\n";
    echo "   O DocumentRoot do subdomínio 'painel' pode ser diferente de '$dir'\n";
} else {
    echo "\n⚠️ Alguns arquivos estão faltando. Verifique os caminhos acima.\n";
}
