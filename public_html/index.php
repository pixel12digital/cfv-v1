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
    
    // Se houver user_id na sessão, manter. Se não, limpar só quando NÃO estiver no fluxo de primeiro acesso.
    // No fluxo "definir senha" (GET /start → POST /define-password) existe onboarding_user_id mas ainda não user_id;
    // zerar a sessão aqui quebrava o definePassword e mandava o usuário de volta pro login.
    if (!empty($_SESSION['user_id'])) {
        // Sessão de usuário logado — validação em AuthController
    } elseif (!empty($_SESSION['onboarding_user_id'])) {
        // Fluxo de primeiro acesso — não limpar; definir senha precisa desses dados
    } else {
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
