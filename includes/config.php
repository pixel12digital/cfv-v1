<?php
// Iniciar buffer de saída o mais cedo possível para evitar "headers already sent"
if (!function_exists('ob_get_level') || ob_get_level() === 0) {
    ob_start();
}

// =====================================================
// CONFIGURAÇÃO PRINCIPAL DO SISTEMA CFC
// =====================================================

// Configurações do Banco de Dados — legado alinha com app quando .env existe (correção 500 legado)
$envPath = __DIR__ . '/../.env';
$dbFromEnv = [];
if (file_exists($envPath) && is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\"'");
        if (in_array($name, ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'], true)) {
            $dbFromEnv[$name] = $value;
        }
    }
}
// Fallback: valores usados pelo legado desde o início (evita quebra se .env não tiver DB_*)
define('DB_HOST', $dbFromEnv['DB_HOST'] ?? 'auth-db803.hstgr.io');
define('DB_NAME', $dbFromEnv['DB_NAME'] ?? 'u502697186_cfcbomconselho');
define('DB_USER', $dbFromEnv['DB_USER'] ?? 'u502697186_cfcbomconselho');
define('DB_PASS', $dbFromEnv['DB_PASS'] ?? 'Los@ngo#081081');
define('DB_CHARSET', 'utf8mb4');

// Configurações da Aplicação
define('APP_NAME', 'Sistema CFC');
define('APP_VERSION', '1.0.0');

// Detectar ambiente automaticamente
function detectEnvironment() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $port = $_SERVER['SERVER_PORT'] ?? '80';
    
    // Detectar homolog antes de outros ambientes (mais específico)
    if (stripos($host, 'homolog') !== false) {
        return 'homolog';
    }
    
    if (in_array($host, ['localhost', '127.0.0.1']) || strpos($host, 'localhost') !== false) {
        return 'local';
    } elseif (strpos($host, 'hostinger') !== false || strpos($host, 'hstgr.io') !== false) {
        return 'production';
    } else {
        return 'production';
    }
}

$environment = detectEnvironment();

// Configurar URL base automaticamente
if ($environment === 'local') {
    $port = $_SERVER['SERVER_PORT'] ?? '80';
    $port_suffix = ($port !== '80' && $port !== '443') ? ':' . $port : '';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $base_path = dirname($script_name);
    if ($base_path === '/') $base_path = '';
    
    define('APP_URL', 'http://localhost' . $port_suffix . $base_path);
    define('BASE_PATH', $base_path);
} elseif ($environment === 'homolog') {
    // URL de homolog será definida em config_homolog.php se necessário
    // Fallback padrão abaixo (será sobrescrito se config_homolog.php definir APP_URL)
    if (!defined('APP_URL')) {
        $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
        $base_path = dirname($script_name);
        if ($base_path === '/') $base_path = '';
        $host = $_SERVER['HTTP_HOST'] ?? 'homolog.local';
        define('APP_URL', 'http://' . $host . $base_path);
        define('BASE_PATH', $base_path);
    }
} else {
    // Em produção, usar o domínio real ou detectar automaticamente
    $productionHost = $_SERVER['HTTP_HOST'] ?? 'cfcbomconselho.com.br';
    // Se for o domínio antigo da Hostinger, usar o domínio correto
    if (strpos($productionHost, 'hostingersite.com') !== false || strpos($productionHost, 'hstgr.io') !== false) {
        $productionHost = 'cfcbomconselho.com.br';
    }
    define('APP_URL', 'https://' . $productionHost);
    define('BASE_PATH', '');
}

define('APP_TIMEZONE', 'America/Sao_Paulo');
define('ENVIRONMENT', $environment);

// Configurações de Segurança baseadas no ambiente
define('JWT_SECRET', 'sua_chave_secreta_muito_segura_aqui');
// Homolog usa configurações similares a local (mais permissivo para testes)
define('SESSION_TIMEOUT', ($environment === 'production') ? 3600 : 7200); // 1 hora para produção, 2 horas para local/homolog
define('MAX_LOGIN_ATTEMPTS', ($environment === 'production') ? 3 : 10); // 3 para produção, 10 para local/homolog
define('LOGIN_TIMEOUT', ($environment === 'production') ? 900 : 1800); // 15 minutos para produção, 30 para local/homolog

// Configurações de Upload baseadas no ambiente
define('UPLOAD_MAX_SIZE', $environment === 'production' ? 5242880 : 10485760); // 5MB para produção, 10MB para local
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// Configurações de Email
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu_email@seudominio.com');
define('SMTP_PASS', 'sua_senha_smtp');

// Configurações de Recuperação de Senha
define('PASSWORD_RESET_SHOW_MASKED_DESTINATION', true); // Mostrar destino mascarado (e-mail/telefone) quando seguro

// Configurações de Log baseadas no ambiente
define('LOG_ENABLED', true);
define('LOG_LEVEL', ($environment === 'production') ? 'INFO' : 'DEBUG'); // INFO para produção, DEBUG para local/homolog

// Configurações de API baseadas no ambiente
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', $environment === 'production' ? 100 : 10000); // 100 para produção, 10000 para local

// Configurações de Recaptcha
define('RECAPTCHA_SITE_KEY', 'sua_chave_site_recaptcha');
define('RECAPTCHA_SECRET_KEY', 'sua_chave_secreta_recaptcha');

// Configurações de Paginação
define('ITEMS_PER_PAGE', 20);

// Configurações de Backup baseadas no ambiente
define('BACKUP_ENABLED', $environment === 'production');
define('BACKUP_FREQUENCY', $environment === 'production' ? 'daily' : 'never'); // daily para produção, never para local

// Configurações de Cache baseadas no ambiente
define('CACHE_ENABLED', $environment === 'production');
define('CACHE_DURATION', $environment === 'production' ? 3600 : 0); // 1 hora para produção, 0 para local

// Configurações de Debug baseadas no ambiente
// Debug ativo em local e homolog (para facilitar testes)
define('DEBUG_MODE', ($environment === 'local' || $environment === 'homolog'));
define('SHOW_ERRORS', ($environment === 'local' || $environment === 'homolog'));

// Configurações de Idioma
define('DEFAULT_LANGUAGE', 'pt-BR');
define('AVAILABLE_LANGUAGES', ['pt-BR', 'en-US']);

// Configurações de Notificações
define('NOTIFICATIONS_ENABLED', true);
define('EMAIL_NOTIFICATIONS', true);
define('SMS_NOTIFICATIONS', false);

// Configurações de Relatórios
define('REPORTS_ENABLED', true);
define('REPORTS_RETENTION_DAYS', 365);

// Configurações de Auditoria
define('AUDIT_ENABLED', true);
define('AUDIT_RETENTION_DAYS', 2555); // 7 anos

// Configurações de Backup Automático baseadas no ambiente
define('AUTO_BACKUP_ENABLED', $environment === 'production');
define('AUTO_BACKUP_TIME', $environment === 'production' ? '02:00' : '00:00'); // 2:00 AM para produção, 00:00 para local
define('AUTO_BACKUP_RETENTION', $environment === 'production' ? 30 : 0); // 30 dias para produção, 0 para local

// Configurações de Manutenção
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'Sistema em manutenção. Tente novamente em alguns minutos.');

// Configurações de Performance baseadas no ambiente
define('COMPRESSION_ENABLED', $environment === 'production');
define('MINIFICATION_ENABLED', $environment === 'production');
define('CDN_ENABLED', false);

// Configurações de SEO
define('SEO_ENABLED', true);
define('META_DESCRIPTION', 'Sistema completo para gestão de Centros de Formação de Condutores');
define('META_KEYWORDS', 'CFC, auto escola, formação condutores, gestão CFC');

// Configurações de Integração
define('INTEGRATION_GOOGLE_ANALYTICS', false);
define('INTEGRATION_FACEBOOK_PIXEL', false);
define('INTEGRATION_WHATSAPP', false);

// Configurações do Sistema Financeiro
define('FINANCEIRO_ENABLED', true);

// Configurações de Suporte
define('SUPPORT_EMAIL', 'suporte@seudominio.com');
define('SUPPORT_PHONE', '(11) 99999-9999');
define('SUPPORT_HOURS', 'Segunda a Sexta, 8h às 18h');

// Configurações de Licenciamento
define('LICENSE_KEY', '');
define('LICENSE_TYPE', 'free'); // free, basic, premium, enterprise
define('LICENSE_EXPIRES', null);

// Configurações de Atualizações
define('AUTO_UPDATE_ENABLED', false);
define('UPDATE_CHECK_FREQUENCY', 'weekly'); // daily, weekly, monthly

// Configurações de Monitoramento baseadas no ambiente
define('MONITORING_ENABLED', $environment === 'production');
define('MONITORING_INTERVAL', $environment === 'production' ? 300 : 0); // 5 minutos para produção, 0 para local
define('ALERT_EMAIL', 'admin@seudominio.com');

// Configurações de Backup em Nuvem
define('CLOUD_BACKUP_ENABLED', false);
define('CLOUD_PROVIDER', ''); // aws, google, azure
define('CLOUD_BUCKET', '');
define('CLOUD_ACCESS_KEY', '');
define('CLOUD_SECRET_KEY', '');

// Configurações de API Externa
define('VIA_CEP_API', 'https://viacep.com.br/ws/');
define('IBGE_API', 'https://servicodados.ibge.gov.br/api/v1/');
define('DETRAN_API', ''); // API do DETRAN se disponível

// Configurações de Notificações Push
define('PUSH_NOTIFICATIONS_ENABLED', false);
define('FCM_SERVER_KEY', '');
define('FCM_SENDER_ID', '');

// Configurações de Chat
define('CHAT_ENABLED', false);
define('CHAT_PROVIDER', ''); // tawk, crisp, intercom
define('CHAT_WIDGET_ID', '');

// Configurações de Analytics
define('ANALYTICS_ENABLED', false);
define('ANALYTICS_PROVIDER', ''); // google, matomo, plausible
define('ANALYTICS_TRACKING_ID', '');

// Configurações de Segurança Avançada
define('2FA_ENABLED', false);
define('PASSWORD_POLICY_ENABLED', true);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);

// Configurações de Rate Limiting baseadas no ambiente
define('RATE_LIMIT_ENABLED', $environment === 'production');
define('RATE_LIMIT_WINDOW', $environment === 'production' ? 3600 : 0); // 1 hora para produção, 0 para local
define('RATE_LIMIT_MAX_REQUESTS', $environment === 'production' ? 1000 : 10000); // 1000 para produção, 10000 para local

// Configurações de Logs de Sistema baseadas no ambiente
define('SYSTEM_LOG_ENABLED', true);
define('SYSTEM_LOG_LEVEL', $environment === 'production' ? 'INFO' : 'DEBUG');
define('SYSTEM_LOG_RETENTION_DAYS', $environment === 'production' ? 90 : 30);

// Configurações de Cache de Banco baseadas no ambiente
define('DB_CACHE_ENABLED', $environment === 'production');
define('DB_CACHE_DURATION', $environment === 'production' ? 1800 : 0); // 30 minutos para produção, 0 para local
define('DB_CACHE_PREFIX', 'cfc_');

// Configurações de Sessão baseadas no ambiente
define('SESSION_NAME', 'CFC_SESSION');
define('SESSION_COOKIE_LIFETIME', 0);
define('SESSION_COOKIE_PATH', '/');
define('SESSION_COOKIE_DOMAIN', $environment === 'production' ? '' : '');  // DOMÍNIO CORRIGIDO
define('SESSION_COOKIE_SECURE', $environment === 'production'); // true para HTTPS em produção, false para HTTP em desenvolvimento
define('SESSION_COOKIE_HTTPONLY', true);

// Configurações de Timeout baseadas no ambiente
define('REQUEST_TIMEOUT', $environment === 'production' ? 30 : 60); // 30 segundos para produção, 60 para local
define('SCRIPT_TIMEOUT', $environment === 'production' ? 300 : 600); // 5 minutos para produção, 10 para local
define('DB_TIMEOUT', $environment === 'production' ? 30 : 60); // 30 segundos para produção, 60 para local

// Configurações de Arquivos
define('FILE_UPLOAD_TEMP_DIR', sys_get_temp_dir());
define('FILE_UPLOAD_MAX_FILES', 10);
define('FILE_DOWNLOAD_CHUNK_SIZE', 8192); // 8KB

// Configurações de Validação baseadas no ambiente
define('VALIDATION_STRICT_MODE', $environment === 'production');
define('VALIDATION_SANITIZE_INPUT', true);
define('VALIDATION_ESCAPE_OUTPUT', true);

// Configurações de Internacionalização baseadas no ambiente
define('I18N_ENABLED', $environment === 'production');
define('I18N_DEFAULT_LOCALE', 'pt_BR');
define('I18N_FALLBACK_LOCALE', 'en_US');

// Configurações de Testes baseadas no ambiente
define('TESTING_MODE', $environment === 'local');
define('TEST_DATABASE_PREFIX', 'test_');
define('TEST_EMAIL_SUFFIX', '@test.local');

// Configurações de Desenvolvimento
define('DEV_MODE', $environment === 'local');
define('DEV_EMAIL', 'dev@seudominio.com');
define('DEV_NOTIFICATIONS', $environment === 'local');

// Configurações de Produção
define('PROD_MODE', $environment === 'production');
define('PROD_EMAIL', 'admin@seudominio.com');
define('PROD_NOTIFICATIONS', $environment === 'production');

// Configurações de Staging
define('STAGING_MODE', false);
define('STAGING_EMAIL', 'staging@seudominio.com');
define('STAGING_NOTIFICATIONS', false);

// Configurações de Homologação
define('HOMOLOG_MODE', $environment === 'homolog');
define('HOMOLOG_EMAIL', 'homolog@seudominio.com');
define('HOMOLOG_NOTIFICATIONS', $environment === 'homolog');

// Configurações de Local
define('LOCAL_MODE', $environment === 'local');
define('LOCAL_EMAIL', 'local@seudominio.com');
define('LOCAL_NOTIFICATIONS', $environment === 'local');

// Configurações de Ambiente
// NOTA: Estas configurações já foram definidas acima, não redefinir

// Configurações de Timezone
date_default_timezone_set(APP_TIMEZONE);

// Configurações de Erro
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// CARREGAR CONFIGURAÇÕES ESPECÍFICAS POR AMBIENTE ANTES da sessão ser iniciada
if ($environment === 'local' && file_exists(__DIR__ . '/../config_local.php')) {
    require_once __DIR__ . '/../config_local.php';
}

// Carregar configurações de homologação (sobrescreve configurações padrão)
if ($environment === 'homolog' && file_exists(__DIR__ . '/../config_homolog.php')) {
    require_once __DIR__ . '/../config_homolog.php';
}

// Configurações de Sessão ANTES de iniciar a sessão
if (!headers_sent() && session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    ini_set('session.cookie_lifetime', SESSION_COOKIE_LIFETIME);
    ini_set('session.cookie_path', SESSION_COOKIE_PATH);
    ini_set('session.cookie_domain', SESSION_COOKIE_DOMAIN);
    ini_set('session.cookie_secure', SESSION_COOKIE_SECURE);
    ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY);
}

// INICIAR SESSÃO IMEDIATAMENTE após todas as definições (apenas se headers não foram enviados)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_name(SESSION_NAME);
    session_start();
}

// Configurações de Upload
if (!headers_sent()) {
    ini_set('upload_max_filesize', UPLOAD_MAX_SIZE);
    ini_set('post_max_size', UPLOAD_MAX_SIZE * 2);
    ini_set('max_file_uploads', FILE_UPLOAD_MAX_FILES);
}

// Configurações de Timeout
ini_set('max_execution_time', SCRIPT_TIMEOUT);
ini_set('max_input_time', REQUEST_TIMEOUT);
ini_set('default_socket_timeout', REQUEST_TIMEOUT);

// Configurações de Memória baseadas no ambiente
ini_set('memory_limit', $environment === 'production' ? '256M' : '512M');

// Configurações de Cache baseadas no ambiente
if (CACHE_ENABLED && $environment === 'production') {
    ini_set('opcache.enable', 1);
    ini_set('opcache.memory_consumption', 128);
    ini_set('opcache.interned_strings_buffer', 8);
    ini_set('opcache.max_accelerated_files', 4000);
    ini_set('opcache.revalidate_freq', 60);
    ini_set('opcache.fast_shutdown', 1);
} else {
    ini_set('opcache.enable', 0);
}

// Configurações de Compressão baseadas no ambiente
if (COMPRESSION_ENABLED && $environment === 'production' && !headers_sent()) {
    ini_set('zlib.output_compression', 1);
    ini_set('zlib.output_compression_level', 6);
} else {
    if (!headers_sent()) {
        ini_set('zlib.output_compression', 0);
    }
}

// Configurações de Segurança
ini_set('expose_php', 0);
ini_set('allow_url_fopen', 0);
ini_set('allow_url_include', 0);
ini_set('file_uploads', 1);
ini_set('max_input_vars', 1000);

// Configurações de Log
if (LOG_ENABLED) {
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
}

// Configurações de Headers de Segurança baseadas no ambiente
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    // Headers específicos para produção
    if ($environment === 'production') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Content-Type-Options: nosniff');
        
        if (defined('APP_URL') && APP_URL) {
            header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://kit.fontawesome.com https://unpkg.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src \'self\' data: https:; connect-src \'self\' https://viacep.com.br https://cdn.jsdelivr.net https://unpkg.com; object-src \'none\'; base-uri \'self\';');
        }
    }
    
    // Headers para desenvolvimento e homologação
    if ($environment === 'local') {
        header('X-Environment: LOCAL');
        header('X-Debug: ENABLED');
        header('X-Development: TRUE');
    } elseif ($environment === 'homolog') {
        header('X-Environment: HOMOLOG');
        header('X-Debug: ENABLED');
        header('X-Testing: TRUE');
    } elseif ($environment === 'homolog') {
        header('X-Environment: HOMOLOG');
        header('X-Debug: ENABLED');
        header('X-Testing: TRUE');
        
        // CSP para desenvolvimento local - TEMPORARIAMENTE DESABILITADO PARA DEBUG
        // header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://www.google.com https://www.gstatic.com https://cdn.jsdelivr.net https://kit.fontawesome.com https://unpkg.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src \'self\' data: https:; connect-src \'self\' https://viacep.com.br https://cdn.jsdelivr.net https://unpkg.com; object-src \'none\'; base-uri \'self\';');
    }
}

// Configurações de Autoload baseadas no ambiente
// COMENTADO TEMPORARIAMENTE - está tentando carregar de diretório inexistente
/*
spl_autoload_register(function ($class) use ($environment) {
    $file = __DIR__ . '/../classes/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    } elseif ($environment === 'local') {
        // Log de debug para desenvolvimento
        error_log('[AUTOLOAD] Classe não encontrada: ' . $class . ' em ' . $file);
    }
});
*/

// Configurações de Funções Globais baseadas no ambiente
if (!function_exists('cfc_config')) {
    function cfc_config($key, $default = null) {
        return defined($key) ? constant($key) : $default;
    }
}

if (!function_exists('cfc_env')) {
    function cfc_env($key, $default = null) {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
}

if (!function_exists('cfc_is_production')) {
    function cfc_is_production() {
        return !DEBUG_MODE && PROD_MODE;
    }
}

if (!function_exists('cfc_is_development')) {
    function cfc_is_development() {
        return DEBUG_MODE && !PROD_MODE;
    }
}

if (!function_exists('cfc_is_local')) {
    function cfc_is_local() {
        return LOCAL_MODE || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
    }
}

if (!function_exists('cfc_get_environment')) {
    function cfc_get_environment() {
        return defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown';
    }
}

if (!function_exists('cfc_is_debug')) {
    function cfc_is_debug() {
        return DEBUG_MODE;
    }
}

if (!function_exists('cfc_get_base_path')) {
    function cfc_get_base_path() {
        return defined('BASE_PATH') ? BASE_PATH : '';
    }
}

// Configurações de Constantes de Ambiente
define('IS_PRODUCTION', $environment === 'production');
define('IS_DEVELOPMENT', ($environment === 'local' || $environment === 'homolog'));
define('IS_LOCAL', $environment === 'local');
define('IS_HOMOLOG', $environment === 'homolog');

// Configurações de Log de Inicialização baseadas no ambiente
if (LOG_ENABLED) {
    $log_message = 'Sistema CFC inicializado com sucesso em ' . date('Y-m-d H:i:s') . ' - Ambiente: ' . $environment;
    if ($environment === 'local') {
        error_log('[LOCAL] ' . $log_message);
    } elseif ($environment === 'homolog') {
        error_log('[HOMOLOG] ' . $log_message);
    } else {
        error_log('[PROD] ' . $log_message);
    }
}

// Configurações de Verificação de Manutenção baseadas no ambiente
if (MAINTENANCE_MODE && $environment === 'production' && !cfc_is_admin()) {
    http_response_code(503);
    die(MAINTENANCE_MESSAGE);
}

// Função para verificar se é admin (simplificada)
function cfc_is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Configurações de Finalização baseadas no ambiente
register_shutdown_function(function() use ($environment) {
    if (LOG_ENABLED) {
        $log_message = 'Sistema CFC finalizado em ' . date('Y-m-d H:i:s') . ' - Ambiente: ' . $environment;
        if ($environment === 'local') {
            error_log('[LOCAL] ' . $log_message);
        } elseif ($environment === 'homolog') {
            error_log('[HOMOLOG] ' . $log_message);
        } else {
            error_log('[PROD] ' . $log_message);
        }
    }
});

?>
