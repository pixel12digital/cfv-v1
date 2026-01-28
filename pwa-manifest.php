<?php
/**
 * Manifest PWA - Versão Isolada
 * 
 * CRÍTICO: Este arquivo é 100% isolado e NÃO depende de:
 * - Banco de dados
 * - Frameworks
 * - Bootstrap
 * - Sessões
 * 
 * Sempre retorna JSON válido, mesmo em caso de erro.
 * 
 * CRÍTICO: Este arquivo NÃO deve ter BOM (Byte Order Mark) ou espaços antes do <?php
 */

// Desabilitar TODOS os outputs de erro
ini_set('display_errors', 0);
ini_set('log_errors', 0);
error_reporting(0);

// Limpar qualquer output buffer anterior
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Função helper para gerar URL absoluta (isolada, não depende de nada)
function getBaseUrl($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Detectar se é localhost
    $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || 
                  strpos($host, 'localhost') !== false ||
                  strpos($host, '127.0.0.1') !== false;
    
    // Em desenvolvimento (localhost), usar caminho completo
    if ($isLocalhost) {
        $basePath = '/cfc-v.1/public_html/';
    } else {
        $basePath = '/';
    }
    
    $path = ltrim($path, '/');
    if ($path) {
        return $protocol . '://' . $host . $basePath . $path;
    } else {
        return $protocol . '://' . $host . rtrim($basePath, '/');
    }
}

// Manifest padrão (sempre válido)
$manifest = [
    'name' => 'CFC Bom Conselho',
    'short_name' => 'CFC',
    'description' => 'Sistema de gestão para Centro de Formação de Condutores',
    'start_url' => getBaseUrl('dashboard'),
    'scope' => getBaseUrl(''),
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'theme_color' => '#023A8D',
    'background_color' => '#ffffff',
    'icons' => [
        [
            'src' => getBaseUrl('pwa/icons/icon-192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => getBaseUrl('pwa/icons/icon-192-maskable.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ],
        [
            'src' => getBaseUrl('pwa/icons/icon-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => getBaseUrl('pwa/icons/icon-512-maskable.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ]
    ]
];

// Limpar buffer completamente antes de qualquer output
ob_clean();

// CRÍTICO: Definir headers ANTES de qualquer output
if (!headers_sent()) {
    header('Content-Type: application/manifest+json; charset=utf-8', true);
    header('Cache-Control: public, max-age=300', true);
    header('X-Content-Type-Options: nosniff', true);
}

// Output JSON (sempre válido)
$json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Se houver erro na codificação (não deveria acontecer), usar JSON mínimo válido
if (json_last_error() !== JSON_ERROR_NONE) {
    ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/manifest+json; charset=utf-8', true);
    }
    // JSON mínimo válido como último recurso
    $json = '{"name":"CFC Sistema","short_name":"CFC","display":"standalone"}';
}

// Output direto sem espaços
echo $json;

// Finalizar e sair imediatamente
ob_end_flush();
exit(0);
