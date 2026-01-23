<?php
/**
 * Diagnóstico PWA - Verificação de Acessibilidade
 * 
 * Este script verifica:
 * 1. Onde os arquivos PWA estão fisicamente
 * 2. Se estão acessíveis publicamente
 * 3. Qual é o DocumentRoot real
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico PWA - Acessibilidade</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico PWA - Acessibilidade</h1>
    
    <div class="section">
        <h2>A) Verificação de Existência Física</h2>
        <?php
        $files = [
            'sw.js' => __DIR__ . '/sw.js',
            'sw.php' => __DIR__ . '/sw.php',
            'pwa-manifest.php' => __DIR__ . '/pwa-manifest.php',
        ];
        
        echo "<h3>Arquivos no diretório atual:</h3>";
        echo "<pre>";
        echo "Diretório atual: " . __DIR__ . "\n";
        echo "DocumentRoot (SCRIPT_FILENAME): " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
        echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
        echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
        echo "\n";
        
        foreach ($files as $name => $path) {
            $exists = file_exists($path);
            $readable = $exists ? is_readable($path) : false;
            $size = $exists ? filesize($path) : 0;
            
            echo sprintf(
                "%s: %s (tamanho: %d bytes, legível: %s)\n",
                $name,
                $exists ? '<span class="success">✅ EXISTE</span>' : '<span class="error">❌ NÃO EXISTE</span>',
                $size,
                $readable ? 'SIM' : 'NÃO'
            );
        }
        echo "</pre>";
        ?>
    </div>
    
    <div class="section">
        <h2>B) Teste de Acesso Público (via cURL)</h2>
        <p class="info">⚠️ Execute estes comandos no servidor ou via SSH:</p>
        <pre>
# Testar sw.js
curl -i https://painel.cfcbomconselho.com.br/sw.js

# Testar sw.php  
curl -i https://painel.cfcbomconselho.com.br/sw.php

# Testar pwa-manifest.php
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php
        </pre>
        
        <h3>Teste Local (via PHP):</h3>
        <?php
        $baseUrl = 'https://painel.cfcbomconselho.com.br';
        $testFiles = ['sw.js', 'sw.php', 'pwa-manifest.php'];
        
        foreach ($testFiles as $file) {
            $url = $baseUrl . '/' . $file;
            echo "<h4>Testando: $file</h4>";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);
            curl_close($ch);
            
            echo "<pre>";
            if ($error) {
                echo "<span class='error'>Erro cURL: $error</span>\n";
            } else {
                echo "Status HTTP: ";
                if ($httpCode == 200) {
                    echo "<span class='success'>✅ $httpCode OK</span>\n";
                } else {
                    echo "<span class='error'>❌ $httpCode</span>\n";
                }
                echo "Content-Type: " . ($contentType ?: 'N/A') . "\n";
                echo "URL: $url\n";
                
                // Fazer GET para ver o conteúdo
                if ($httpCode == 200) {
                    $ch2 = curl_init($url);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                    $body = curl_exec($ch2);
                    curl_close($ch2);
                    
                    $firstChars = substr($body, 0, 200);
                    echo "\nPrimeiros 200 caracteres do body:\n";
                    echo htmlspecialchars($firstChars) . "\n";
                    
                    // Verificar se é JSON válido (para manifest)
                    if ($file == 'pwa-manifest.php') {
                        $json = json_decode($body, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            echo "<span class='success'>✅ JSON válido</span>\n";
                        } else {
                            echo "<span class='error'>❌ JSON inválido: " . json_last_error_msg() . "</span>\n";
                        }
                    }
                }
            }
            echo "</pre>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>C) Informações do Servidor</h2>
        <pre>
<?php
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "\n";
?>
        </pre>
    </div>
    
    <div class="section">
        <h2>D) Estrutura de Diretórios</h2>
        <pre>
<?php
function listDirRecursive($dir, $maxDepth = 3, $currentDepth = 0, $prefix = '') {
    if ($currentDepth >= $maxDepth) return;
    if (!is_dir($dir)) return;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        if (strpos($item, '.') === 0 && $item != '.htaccess') continue;
        
        $path = $dir . '/' . $item;
        $isDir = is_dir($path);
        
        echo $prefix . ($isDir ? '📁 ' : '📄 ') . $item . "\n";
        
        if ($isDir && $currentDepth < $maxDepth - 1) {
            listDirRecursive($path, $maxDepth, $currentDepth + 1, $prefix . '  ');
        }
    }
}

echo "Estrutura a partir de " . __DIR__ . ":\n\n";
listDirRecursive(__DIR__, 2);
?>
        </pre>
    </div>
    
    <div class="section">
        <h2>E) Próximos Passos</h2>
        <ol>
            <li><strong>Verificar DocumentRoot:</strong> Confirme no painel da Hostinger qual é o DocumentRoot do subdomínio <code>painel</code></li>
            <li><strong>Copiar arquivos:</strong> Se o DocumentRoot for diferente, copie <code>sw.js</code>, <code>sw.php</code> e <code>pwa-manifest.php</code> para o DocumentRoot correto</li>
            <li><strong>Testar acesso:</strong> Execute os comandos curl acima e verifique se retornam 200 OK</li>
            <li><strong>Verificar .htaccess:</strong> Confirme que o .htaccess no DocumentRoot permite acesso direto a esses arquivos</li>
        </ol>
    </div>
</body>
</html>
