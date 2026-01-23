<?php
/**
 * Diagnóstico PWA - Versão Simplificada para Raiz
 * 
 * Coloque este arquivo na RAIZ do DocumentRoot do subdomínio painel
 * Exemplo: Se DocumentRoot for /public_html/, coloque em /public_html/diagnostico-pwa.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico PWA</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
        code { background: #f0f0f0; padding: 2px 5px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico PWA - Acessibilidade</h1>
    
    <div class="section">
        <h2>1. Informações do Servidor</h2>
        <pre>
<?php
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'N/A') . "\n";
echo "Diretório atual (__DIR__): " . __DIR__ . "\n";
?>
        </pre>
    </div>
    
    <div class="section">
        <h2>2. Verificação de Arquivos PWA</h2>
        <?php
        $files = [
            'sw.js' => __DIR__ . '/sw.js',
            'sw.php' => __DIR__ . '/sw.php',
            'pwa-manifest.php' => __DIR__ . '/pwa-manifest.php',
        ];
        
        echo "<pre>";
        foreach ($files as $name => $path) {
            $exists = file_exists($path);
            $readable = $exists ? is_readable($path) : false;
            $size = $exists ? filesize($path) : 0;
            
            $status = $exists ? '<span class="success">✅ EXISTE</span>' : '<span class="error">❌ NÃO EXISTE</span>';
            $readStatus = $readable ? 'SIM' : 'NÃO';
            
            echo sprintf("%s: %s\n", $name, $status);
            echo sprintf("  Caminho: %s\n", $path);
            echo sprintf("  Tamanho: %d bytes\n", $size);
            echo sprintf("  Legível: %s\n\n", $readStatus);
        }
        echo "</pre>";
        ?>
    </div>
    
    <div class="section">
        <h2>3. Teste de Acesso Público</h2>
        <p class="warning">⚠️ Execute estes comandos no servidor (SSH) ou localmente:</p>
        <pre>
curl -i https://painel.cfcbomconselho.com.br/sw.js
curl -i https://painel.cfcbomconselho.com.br/sw.php
curl -i https://painel.cfcbomconselho.com.br/pwa-manifest.php
        </pre>
        
        <h3>Teste via PHP (pode não funcionar se houver bloqueios):</h3>
        <?php
        $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'painel.cfcbomconselho.com.br');
        $testFiles = ['sw.js', 'sw.php', 'pwa-manifest.php'];
        
        foreach ($testFiles as $file) {
            $url = $baseUrl . '/' . $file;
            echo "<h4>Testando: <code>$file</code></h4>";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
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
                if ($httpCode == 200) {
                    echo "<span class='success'>✅ Status: $httpCode OK</span>\n";
                } else {
                    echo "<span class='error'>❌ Status: $httpCode</span>\n";
                }
                echo "Content-Type: " . ($contentType ?: 'N/A') . "\n";
                echo "URL: $url\n";
            }
            echo "</pre>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. Instruções para Correção</h2>
        <h3>Se os arquivos NÃO EXISTEM no DocumentRoot:</h3>
        <ol>
            <li><strong>Identifique o DocumentRoot:</strong>
                <ul>
                    <li>No painel Hostinger: <strong>Domínios</strong> → <strong>Subdomínios</strong> → <code>painel</code></li>
                    <li>Veja onde está apontando (ex: <code>/public_html/</code> ou <code>/public_html/painel/public_html/</code>)</li>
                </ul>
            </li>
            <li><strong>Copie os arquivos para o DocumentRoot:</strong>
                <pre>
# Via SSH (ajuste os caminhos conforme seu servidor):
cp /caminho/para/public_html/sw.js /DocumentRoot/sw.js
cp /caminho/para/public_html/sw.php /DocumentRoot/sw.php
cp /caminho/para/public_html/pwa-manifest.php /DocumentRoot/pwa-manifest.php
                </pre>
            </li>
            <li><strong>Ou via File Manager da Hostinger:</strong>
                <ul>
                    <li>Navegue até o DocumentRoot do subdomínio <code>painel</code></li>
                    <li>Faça upload dos arquivos: <code>sw.js</code>, <code>sw.php</code>, <code>pwa-manifest.php</code></li>
                </ul>
            </li>
        </ol>
        
        <h3>Se os arquivos EXISTEM mas retornam 404:</h3>
        <ol>
            <li><strong>Verifique o .htaccess:</strong>
                <ul>
                    <li>Deve permitir acesso direto antes do front controller</li>
                    <li>Deve ter: <code>RewriteRule ^sw\.(js|php)$ - [L]</code></li>
                    <li>Deve ter: <code>RewriteRule ^pwa-manifest\.php$ - [L]</code></li>
                </ul>
            </li>
            <li><strong>Verifique permissões:</strong>
                <ul>
                    <li>Arquivos: 644 ou 755</li>
                    <li>Diretório: 755</li>
                </ul>
            </li>
        </ol>
    </div>
    
    <div class="section">
        <h2>5. Estrutura de Diretórios Atual</h2>
        <pre>
<?php
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
        
        echo $prefix . ($isDir ? '📁 ' : '📄 ') . $item . "\n";
        
        if ($isDir && $currentDepth < $maxDepth - 1) {
            listDir($path, $maxDepth, $currentDepth + 1, $prefix . '  ');
        }
    }
}

echo "Estrutura a partir de " . __DIR__ . ":\n\n";
listDir(__DIR__, 2);
?>
        </pre>
    </div>
</body>
</html>
