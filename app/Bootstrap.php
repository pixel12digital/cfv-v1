<?php

// Session — mesmo nome do legado (includes/config.php SESSION_NAME) para /aluno/dashboard.php enxergar sessão pós define-password
if (session_status() === PHP_SESSION_NONE) {
    session_name('CFC_SESSION');
    session_start([
        'cookie_lifetime' => 86400, // 24 horas
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Lax' // Lax permite navegação top-level de links externos (wa.me, email)
    ]);
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Helper functions
if (!function_exists('base_path')) {
    function base_path($path = '') {
        // Detectar ambiente de forma mais robusta
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Detectar produção: se APP_ENV=production OU se host não contém localhost/127.0.0.1
        $isProduction = false;
        if ($appEnv === 'production') {
            $isProduction = true;
        } elseif ($appEnv === null || $appEnv === 'local') {
            // Se não tem APP_ENV definido, detectar pelo hostname E pelo SCRIPT_NAME
            $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || 
                          strpos($host, 'localhost') !== false ||
                          strpos($host, '127.0.0.1') !== false;
            
            // Se SCRIPT_NAME contém /cfc-v.1/ ou /public_html/, é local
            $isLocalPath = strpos($scriptName, '/cfc-v.1/') !== false || 
                          strpos($scriptName, '/public_html/') !== false;
            
            // É produção se NÃO for localhost E NÃO tiver path de desenvolvimento
            $isProduction = !$isLocalhost && !$isLocalPath;
        }
        
        // Em produção, usar base fixo (DocumentRoot geralmente aponta para public_html/)
        if ($isProduction) {
            // Em produção, base é a raiz
            $base = '/';
        } else {
            // Em desenvolvimento (localhost), SEMPRE usar o caminho completo
            // Não depender do SCRIPT_NAME que pode variar com rewrites
            $base = '/cfc-v.1/public_html/';
        }
        
        // Limpar o path
        $path = ltrim($path, '/');
        
        // Montar path: base + / + path (ou apenas base se path vazio)
        // Evitar dupla barra: se base termina com /, não adicionar outra /
        if ($path) {
            if (substr($base, -1) === '/') {
                return $base . $path;
            } else {
                return $base . '/' . $path;
            }
        } else {
            return $base;
        }
    }
}

if (!function_exists('base_url')) {
    function base_url($path = '') {
        // URL completa (para redirects)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Detectar se é localhost (desenvolvimento)
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || 
                      strpos($host, 'localhost') !== false ||
                      strpos($host, '127.0.0.1') !== false;
        
        // Detectar produção: se APP_ENV=production E não é localhost
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        $isProduction = ($appEnv === 'production') && !$isLocalhost;
        
        // Em produção, usar basePath fixo (DocumentRoot geralmente aponta para public_html/)
        if ($isProduction) {
            // Em produção, basePath é a raiz
            $basePath = '/';
        } else {
            // Em desenvolvimento (localhost), SEMPRE usar o caminho completo
            // Não depender do SCRIPT_NAME que pode variar com rewrites
            $basePath = '/cfc-v.1/public_html/';
        }
        
        // Montar URL completa: protocolo + host + basePath + path
        // IMPORTANTE: sempre remover barra inicial do path para evitar problemas
        $path = ltrim($path, '/');
        
        // Evitar dupla barra: se basePath termina com /, não adicionar outra /
        if ($path) {
            if (substr($basePath, -1) === '/') {
                $url = $protocol . '://' . $host . $basePath . $path;
            } else {
                $url = $protocol . '://' . $host . $basePath . '/' . $path;
            }
        } else {
            // Se path vazio, retornar basePath (sem barra final se for raiz)
            $url = $protocol . '://' . $host . rtrim($basePath, '/');
        }
        
        return $url;
    }
}

if (!function_exists('pwa_asset_path')) {
    /**
     * Retorna o path correto para arquivos PWA (manifest, service worker)
     * Detecta automaticamente baseado na estrutura do servidor
     */
    function pwa_asset_path($filename) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Se SCRIPT_NAME contém /public_html/, extrair o path base completo
        // Ex: /public_html/painel/public_html/index.php -> /public_html/painel/public_html/sw.js
        if (preg_match('#(/[^/]+/public_html)/#', $scriptName, $matches)) {
            // Padrão: /public_html/painel/public_html/
            return $matches[1] . '/' . $filename;
        } elseif (preg_match('#(/public_html)/#', $scriptName, $matches)) {
            // Padrão: /public_html/ (sem subdiretório)
            return $matches[1] . '/' . $filename;
        } elseif (strpos($scriptName, '/public_html') !== false) {
            // Fallback: extrair diretório do SCRIPT_NAME
            $base = dirname($scriptName);
            // Garantir que termina com /
            if (substr($base, -1) !== '/') {
                $base .= '/';
            }
            return $base . $filename;
        }
        
        // Se não detectou /public_html/, usar base_path (DocumentRoot = raiz ou outro)
        return base_path($filename);
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path, $versioned = true) {
        // Limpar o path
        $path = ltrim($path, '/'); // ex: css/tokens.css
        
        // Detectar ambiente de forma mais robusta (mesma lógica do base_path)
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? null;
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Detectar se é localhost (desenvolvimento)
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1']) || 
                      strpos($host, 'localhost') !== false ||
                      strpos($host, '127.0.0.1') !== false;
        
        // Em produção (APP_ENV=production E não é localhost), usar path fixo
        if ($appEnv === 'production' && !$isLocalhost) {
            // Em produção, assets devem estar em /assets/ (relativo ao DocumentRoot)
            $url = '/assets/' . $path;
        } else {
            // Em desenvolvimento (localhost), SEMPRE usar o caminho completo
            $url = '/cfc-v.1/public_html/assets/' . $path;
        }
        
        // Cache bust (versionamento)
        if ($versioned) {
            // Tentar encontrar o arquivo no disco
            $fileOnDisk = null;
            
            // Primeiro tentar em public_html/assets/ (estrutura padrão)
            if (defined('ROOT_PATH')) {
                $fileOnDisk = ROOT_PATH . '/public_html/assets/' . $path;
                if (!file_exists($fileOnDisk)) {
                    $fileOnDisk = ROOT_PATH . '/assets/' . $path;
                }
            } else {
                $fileOnDisk = dirname(__DIR__) . '/public_html/assets/' . $path;
                if (!file_exists($fileOnDisk)) {
                    $fileOnDisk = dirname(__DIR__) . '/assets/' . $path;
                }
            }
            
            // Se encontrou o arquivo, adicionar timestamp
            if ($fileOnDisk && is_file($fileOnDisk)) {
                $url .= '?v=' . filemtime($fileOnDisk);
            }
        }
        
        return $url;
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        // Garantir que é uma URL absoluta (com http:// ou https://)
        // Se não começar com http:// ou https://, assumir que é um path e usar base_url()
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = base_url($url);
        }
        
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
