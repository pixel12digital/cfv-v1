<?php

// Verificar se está sendo acessado pelo subdomínio painel
// Se sim, garantir que sempre mostre o login (a menos que haja sessão válida)
$host = $_SERVER['HTTP_HOST'] ?? '';
$isPainelSubdomain = strpos($host, 'painel.') === 0 || $host === 'painel.cfcbomconselho.com.br';

// Se for o subdomínio painel e não houver sessão válida, garantir que mostre login
if ($isPainelSubdomain) {
    // Iniciar sessão com mesmo nome do legado (CFC_SESSION) para /aluno/dashboard.php enxergar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_name('CFC_SESSION');
        session_start();
    }
    
    // Se houver user_id na sessão, verificar se é válido
    if (!empty($_SESSION['user_id'])) {
        // A validação será feita no AuthController::showLogin()
        // Por enquanto, apenas garantir que a sessão está ativa
    } else {
        // Limpar qualquer sessão inválida
        $_SESSION = [];
    }
}

// Inicialização
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', __DIR__);

// Autoload (necessário antes de usar classes)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} else {
    require_once APP_PATH . '/autoload.php';
}

// Carregar variáveis de ambiente PRIMEIRO (para detectar ambiente)
use App\Config\Env;
Env::load();

// Configurar exibição de erros baseado no ambiente
$appEnv = $_ENV['APP_ENV'] ?? 'local';
if ($appEnv === 'production') {
    // Produção: ocultar erros, apenas logar
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    // Garantir que o diretório de logs existe
    $logDir = ROOT_PATH . '/storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
} else {
    // Desenvolvimento: mostrar erros
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Bootstrap
require_once APP_PATH . '/Bootstrap.php';

// Router
use App\Core\Router;

$router = new Router();
$router->dispatch();
