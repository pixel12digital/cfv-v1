<?php
/**
 * Manifest PWA Dinâmico - White-Label
 * 
 * Retorna manifest.json dinâmico baseado no CFC atual (tenant)
 * Fallback seguro para valores estáticos se não conseguir resolver CFC
 * 
 * CRÍTICO: Este arquivo NÃO deve ter BOM (Byte Order Mark) ou espaços antes do <?php
 */

// Desabilitar output de erros para garantir JSON puro
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Limpar qualquer output buffer anterior e iniciar novo (garantir JSON puro)
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Função helper para gerar URL absoluta (se base_url não estiver disponível)
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

// Valores padrão (fallback)
$defaultManifest = [
    'name' => 'CFC Sistema de Gestão',
    'short_name' => 'CFC Sistema',
    'description' => 'Sistema de gestão para Centros de Formação de Condutores',
    'start_url' => getBaseUrl('dashboard'),
    'scope' => getBaseUrl(''),
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'theme_color' => '#023A8D',
    'background_color' => '#ffffff',
    'icons' => [
        [
            'src' => getBaseUrl('icons/1/icon-192x192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => getBaseUrl('icons/1/icon-512x512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

// Tentar carregar dados dinâmicos do CFC (com fallback seguro)
try {
    // Incluir dependências mínimas (com output buffering para evitar warnings)
    $rootPath = dirname(__DIR__);
    
    // Suprimir warnings/notices durante includes (não queremos no JSON)
    $oldErrorReporting = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    
    // 1. Bootstrap (session e helpers)
    if (file_exists($rootPath . '/app/Bootstrap.php')) {
        require_once $rootPath . '/app/Bootstrap.php';
    }
    
    // 2. Constants (constantes do sistema)
    if (file_exists($rootPath . '/app/Config/Constants.php')) {
        require_once $rootPath . '/app/Config/Constants.php';
    }
    
    // 3. Env (configurações)
    if (file_exists($rootPath . '/app/Config/Env.php')) {
        require_once $rootPath . '/app/Config/Env.php';
        \App\Config\Env::load();
    }
    
    // 4. Database (conexão)
    if (file_exists($rootPath . '/app/Config/Database.php')) {
        require_once $rootPath . '/app/Config/Database.php';
    }
    
    // 5. Model base
    if (file_exists($rootPath . '/app/Models/Model.php')) {
        require_once $rootPath . '/app/Models/Model.php';
    }
    
    // 6. Model Cfc
    if (file_exists($rootPath . '/app/Models/Cfc.php')) {
        require_once $rootPath . '/app/Models/Cfc.php';
        
        // Buscar dados do CFC atual
        $cfcModel = new \App\Models\Cfc();
        $cfc = $cfcModel->getCurrent();
        
        if ($cfc && !empty($cfc['nome'])) {
            // Montar manifest dinâmico
            $cfcName = trim($cfc['nome']);
            $shortName = mb_substr($cfcName, 0, 15); // Limitar a 15 caracteres
            
            // Se o nome for muito longo, truncar e adicionar "..."
            if (mb_strlen($cfcName) > 15) {
                $shortName = mb_substr($cfcName, 0, 12) . '...';
            }
            
            // Verificar se há ícones PWA gerados para este CFC
            $icons = $defaultManifest['icons']; // Fallback para ícones padrão
            
            if (!empty($cfc['id'])) {
                // Verificar se os arquivos existem
                $rootPath = dirname(__DIR__);
                $icon192Path = $rootPath . '/public_html/icons/' . $cfc['id'] . '/icon-192x192.png';
                $icon512Path = $rootPath . '/public_html/icons/' . $cfc['id'] . '/icon-512x512.png';
                
                if (file_exists($icon192Path) && file_exists($icon512Path)) {
                    // Usar ícones dinâmicos do CFC com URLs absolutas
                    $icons = [
                        [
                            'src' => getBaseUrl('icons/' . $cfc['id'] . '/icon-192x192.png'),
                            'sizes' => '192x192',
                            'type' => 'image/png',
                            'purpose' => 'any maskable'
                        ],
                        [
                            'src' => getBaseUrl('icons/' . $cfc['id'] . '/icon-512x512.png'),
                            'sizes' => '512x512',
                            'type' => 'image/png',
                            'purpose' => 'any maskable'
                        ]
                    ];
                }
            }
            
            // Usar nome do CFC
            $manifest = [
                'name' => $cfcName,
                'short_name' => $shortName,
                'description' => 'Sistema de gestão para ' . $cfcName,
                'start_url' => getBaseUrl('dashboard'),
                'scope' => getBaseUrl(''),
                'display' => 'standalone',
                'orientation' => 'portrait-primary',
                'theme_color' => '#023A8D', // Pode ser dinâmico no futuro se houver campo theme_color
                'background_color' => '#ffffff',
                'icons' => $icons
            ];
            
        } else {
            // CFC não encontrado ou sem nome - usar fallback
            $manifest = $defaultManifest;
        }
    } else {
        // Model não existe - usar fallback
        $manifest = $defaultManifest;
    }
    
} catch (\Exception $e) {
    // Qualquer erro - usar fallback (nunca retornar 500)
    // Log do erro pode ser feito aqui se necessário, mas não expor ao cliente
    $manifest = $defaultManifest;
} catch (\Throwable $e) {
    // Capturar qualquer erro fatal também
    $manifest = $defaultManifest;
} finally {
    // Restaurar error reporting
    if (isset($oldErrorReporting)) {
        error_reporting($oldErrorReporting);
    }
}

// Limpar buffer completamente e garantir que não há output antes do JSON
ob_clean();

// CRÍTICO: Definir headers ANTES de qualquer output
// Usar replace=true para sobrescrever qualquer header anterior
if (!headers_sent()) {
    header('Content-Type: application/manifest+json; charset=utf-8', true);
    header('Cache-Control: public, max-age=300', true);
    header('X-Content-Type-Options: nosniff', true);
}

// Output JSON (sempre retorna 200 com manifest válido)
// Garantir que não há whitespace ou BOM antes do JSON
$json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Verificar se houve erro na codificação JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    // Se houver erro, usar fallback simples
    ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/manifest+json; charset=utf-8', true);
    }
    $json = json_encode($defaultManifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

// Output direto sem espaços
echo $json;

// Finalizar output buffer e sair imediatamente
ob_end_flush();
exit(0);