<?php
/**
 * Script de Diagnóstico - Dashboard HTTP 500
 * Acesse: https://painel.cfcbomconselho.com.br/tools/diagnostico_dashboard.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico Dashboard - HTTP 500</h1>";
echo "<pre>";

// 1. Verificar se o arquivo index.php existe
echo "=== 1. VERIFICAÇÃO DE ARQUIVOS ===\n";
$indexPath = __DIR__ . '/../index.php';
echo "index.php existe: " . (file_exists($indexPath) ? "SIM" : "NÃO") . "\n";
echo "Caminho: " . $indexPath . "\n\n";

// 2. Verificar se as classes necessárias existem
echo "=== 2. VERIFICAÇÃO DE CLASSES ===\n";
$classes = [
    'App\\Controllers\\DashboardController',
    'App\\Core\\Router',
    'App\\Models\\User',
    'App\\Config\\Database',
    'App\\Bootstrap'
];

foreach ($classes as $class) {
    $exists = class_exists($class) || interface_exists($class);
    echo $class . ": " . ($exists ? "EXISTE" : "NÃO EXISTE") . "\n";
}
echo "\n";

// 3. Verificar sessão
echo "=== 3. VERIFICAÇÃO DE SESSÃO ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Status da sessão: " . (session_status() === PHP_SESSION_ACTIVE ? "ATIVA" : "INATIVA") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "user_id na sessão: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "\n";
echo "current_role na sessão: " . ($_SESSION['current_role'] ?? 'NÃO DEFINIDO') . "\n";
echo "\n";

// 4. Tentar carregar o Bootstrap
echo "=== 4. TESTE DE BOOTSTRAP ===\n";
try {
    define('ROOT_PATH', dirname(__DIR__));
    define('APP_PATH', ROOT_PATH . '/app');
    define('PUBLIC_PATH', __DIR__ . '/..');
    
    if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
        require_once ROOT_PATH . '/vendor/autoload.php';
        echo "✓ Autoload do Composer carregado\n";
    } else {
        require_once APP_PATH . '/autoload.php';
        echo "✓ Autoload customizado carregado\n";
    }
    
    use App\Config\Env;
    Env::load();
    echo "✓ Variáveis de ambiente carregadas\n";
    
    require_once APP_PATH . '/Bootstrap.php';
    echo "✓ Bootstrap carregado\n";
} catch (Exception $e) {
    echo "✗ ERRO ao carregar Bootstrap: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// 5. Tentar instanciar DashboardController
echo "=== 5. TESTE DE DASHBOARD CONTROLLER ===\n";
try {
    use App\Controllers\DashboardController;
    use App\Models\User;
    
    $controller = new DashboardController();
    echo "✓ DashboardController instanciado\n";
    
    // Tentar buscar usuário se houver user_id
    if (!empty($_SESSION['user_id'])) {
        $userModel = new User();
        $user = $userModel->find($_SESSION['user_id']);
        if ($user) {
            echo "✓ Usuário encontrado: " . ($user['nome'] ?? 'N/A') . " (tipo: " . ($user['tipo'] ?? 'N/A') . ")\n";
        } else {
            echo "✗ Usuário não encontrado no banco\n";
        }
    }
} catch (Exception $e) {
    echo "✗ ERRO ao instanciar DashboardController: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// 6. Verificar banco de dados
echo "=== 6. TESTE DE BANCO DE DADOS ===\n";
try {
    $db = \App\Config\Database::getInstance()->getConnection();
    echo "✓ Conexão com banco estabelecida\n";
    
    if (!empty($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT id, nome, email, tipo FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($user) {
            echo "✓ Usuário no banco: " . $user['nome'] . " (tipo: " . $user['tipo'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "✗ ERRO ao conectar ao banco: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. Verificar logs de erro do PHP
echo "=== 7. ÚLTIMOS ERROS DO PHP ===\n";
$logPath = ini_get('error_log');
if ($logPath && file_exists($logPath)) {
    $lines = file($logPath);
    $lastLines = array_slice($lines, -20);
    echo "Últimas 20 linhas do log:\n";
    echo implode('', $lastLines);
} else {
    echo "Log de erros não encontrado ou não configurado\n";
    echo "error_log configurado: " . ($logPath ?: 'NÃO DEFINIDO') . "\n";
}
echo "\n";

// 8. Testar rota diretamente
echo "=== 8. TESTE DE ROTA ===\n";
try {
    use App\Core\Router;
    
    // Simular requisição GET para /dashboard
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/dashboard';
    
    $router = new Router();
    echo "✓ Router instanciado\n";
    echo "⚠️  Não executando dispatch() para evitar redirecionamento\n";
} catch (Exception $e) {
    echo "✗ ERRO ao criar Router: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<hr>";
echo "<h2>Próximos Passos</h2>";
echo "<p>Se algum teste falhou acima, esse é o problema que precisa ser corrigido.</p>";
echo "<p>Verifique especialmente os erros relacionados a:</p>";
echo "<ul>";
echo "<li>Classes não encontradas (verificar autoload)</li>";
echo "<li>Erros de conexão com banco de dados</li>";
echo "<li>Erros de sintaxe no código</li>";
echo "<li>Problemas com sessão</li>";
echo "</ul>";
