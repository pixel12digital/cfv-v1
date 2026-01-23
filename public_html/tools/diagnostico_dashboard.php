<?php
/**
 * Script de Diagnóstico - Dashboard HTTP 500
 * Acesse: https://painel.cfcbomconselho.com.br/tools/diagnostico_dashboard.php
 */

// Permitir acesso direto sem passar pelo router
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico Dashboard</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        pre { background: white; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<h1>Diagnóstico Dashboard - HTTP 500</h1>
<pre>
<?php

// 1. Verificar se o arquivo index.php existe
echo "=== 1. VERIFICAÇÃO DE ARQUIVOS ===\n";
$indexPath = __DIR__ . '/../index.php';
echo "index.php existe: " . (file_exists($indexPath) ? "SIM" : "NÃO") . "\n";
echo "Caminho: " . $indexPath . "\n";
echo "Caminho absoluto: " . realpath($indexPath) . "\n\n";

// 2. Verificar estrutura de diretórios
echo "=== 2. ESTRUTURA DE DIRETÓRIOS ===\n";
$rootPath = dirname(__DIR__);
echo "ROOT_PATH: " . $rootPath . "\n";
echo "APP_PATH: " . $rootPath . "/app\n";
echo "PUBLIC_PATH: " . __DIR__ . "/..\n";
echo "APP_PATH existe: " . (is_dir($rootPath . '/app') ? "SIM" : "NÃO") . "\n\n";

// 3. Verificar sessão
echo "=== 3. VERIFICAÇÃO DE SESSÃO ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Status da sessão: " . (session_status() === PHP_SESSION_ACTIVE ? "ATIVA" : "INATIVA") . "\n";
echo "Session ID: " . session_id() . "\n";
echo "user_id na sessão: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "\n";
echo "current_role na sessão: " . ($_SESSION['current_role'] ?? 'NÃO DEFINIDO') . "\n";
echo "user_email na sessão: " . ($_SESSION['user_email'] ?? 'NÃO DEFINIDO') . "\n";
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
    } elseif (file_exists(APP_PATH . '/autoload.php')) {
        require_once APP_PATH . '/autoload.php';
        echo "✓ Autoload customizado carregado\n";
    } else {
        echo "✗ Nenhum autoload encontrado\n";
        throw new Exception("Autoload não encontrado");
    }
    
    if (class_exists('App\\Config\\Env')) {
        use App\Config\Env;
        Env::load();
        echo "✓ Variáveis de ambiente carregadas\n";
    } else {
        echo "⚠️  Classe Env não encontrada, continuando...\n";
    }
    
    if (file_exists(APP_PATH . '/Bootstrap.php')) {
        require_once APP_PATH . '/Bootstrap.php';
        echo "✓ Bootstrap carregado\n";
    } else {
        echo "✗ Bootstrap.php não encontrado\n";
        throw new Exception("Bootstrap não encontrado");
    }
} catch (Exception $e) {
    echo "✗ ERRO ao carregar Bootstrap: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}
echo "\n";

// 5. Verificar se as classes necessárias existem
echo "=== 5. VERIFICAÇÃO DE CLASSES ===\n";
$classes = [
    'App\\Controllers\\DashboardController',
    'App\\Core\\Router',
    'App\\Models\\User',
    'App\\Config\\Database',
];

foreach ($classes as $class) {
    $exists = class_exists($class);
    echo $class . ": " . ($exists ? "✓ EXISTE" : "✗ NÃO EXISTE") . "\n";
}
echo "\n";

// 6. Tentar instanciar DashboardController
echo "=== 6. TESTE DE DASHBOARD CONTROLLER ===\n";
try {
    if (class_exists('App\\Controllers\\DashboardController')) {
        use App\Controllers\DashboardController;
        $controller = new DashboardController();
        echo "✓ DashboardController instanciado\n";
    } else {
        echo "✗ DashboardController não encontrado\n";
    }
    
    if (!empty($_SESSION['user_id']) && class_exists('App\\Models\\User')) {
        use App\Models\User;
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
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}
echo "\n";

// 7. Verificar banco de dados
echo "=== 7. TESTE DE BANCO DE DADOS ===\n";
try {
    if (class_exists('App\\Config\\Database')) {
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
    } else {
        echo "✗ Classe Database não encontrada\n";
    }
} catch (Exception $e) {
    echo "✗ ERRO ao conectar ao banco: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
}
echo "\n";

// 8. Verificar logs de erro do PHP
echo "=== 8. ÚLTIMOS ERROS DO PHP ===\n";
$logPath = ini_get('error_log');
if ($logPath && file_exists($logPath)) {
    $lines = file($logPath);
    $lastLines = array_slice($lines, -30);
    echo "Últimas 30 linhas do log:\n";
    echo htmlspecialchars(implode('', $lastLines));
} else {
    echo "Log de erros não encontrado ou não configurado\n";
    echo "error_log configurado: " . ($logPath ?: 'NÃO DEFINIDO') . "\n";
    
    // Tentar encontrar logs em locais comuns
    $possibleLogs = [
        ROOT_PATH . '/storage/logs/php_errors.log',
        ROOT_PATH . '/logs/php_errors.log',
        '/var/log/apache2/error.log',
        '/var/log/httpd/error_log'
    ];
    
    foreach ($possibleLogs as $log) {
        if (file_exists($log)) {
            echo "Log encontrado em: " . $log . "\n";
            $lines = file($log);
            $lastLines = array_slice($lines, -20);
            echo "Últimas 20 linhas:\n";
            echo htmlspecialchars(implode('', $lastLines));
            break;
        }
    }
}
echo "\n";

// 9. Informações do servidor
echo "=== 9. INFORMAÇÕES DO SERVIDOR ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "\n";

?>
</pre>
<hr>
<h2>Próximos Passos</h2>
<p>Se algum teste falhou acima, esse é o problema que precisa ser corrigido.</p>
<p>Verifique especialmente os erros relacionados a:</p>
<ul>
    <li>Classes não encontradas (verificar autoload)</li>
    <li>Erros de conexão com banco de dados</li>
    <li>Erros de sintaxe no código</li>
    <li>Problemas com sessão</li>
</ul>
<p><strong>Se o arquivo não estiver acessível, verifique:</strong></p>
<ul>
    <li>Se o arquivo foi enviado para o servidor</li>
    <li>Se as permissões do arquivo estão corretas (644)</li>
    <li>Se o DocumentRoot está apontando para public_html/</li>
</ul>
</body>
</html>
