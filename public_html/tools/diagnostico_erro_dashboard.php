<?php
/**
 * Script de Diagnóstico - Erro Dashboard Instrutor
 * Execute no servidor: https://painel.cfcbomconselho.com.br/tools/diagnostico_erro_dashboard.php
 * 
 * Este script verifica:
 * 1. Se o código com logging está em produção
 * 2. Onde está o error_log do PHP
 * 3. Tenta reproduzir o erro e capturar SQLSTATE
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico - Erro Dashboard Instrutor</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; font-size: 14px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; border: 1px solid #ddd; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico - Erro Dashboard Instrutor</h1>

<?php
echo "<div class='section'>";
echo "<h2>1. Verificação de Código em Produção</h2>";

// Verificar commit atual
$gitPath = dirname(__DIR__, 2);
$commitHash = null;
$commitMessage = null;

if (is_dir($gitPath . '/.git')) {
    $commitHash = @shell_exec("cd " . escapeshellarg($gitPath) . " && git rev-parse --short HEAD 2>&1");
    $commitMessage = @shell_exec("cd " . escapeshellarg($gitPath) . " && git log -1 --oneline 2>&1");
    
    if ($commitHash) {
        echo "<p><strong>Commit atual:</strong> <code>" . htmlspecialchars(trim($commitHash)) . "</code></p>";
        echo "<p><strong>Mensagem:</strong> " . htmlspecialchars(trim($commitMessage)) . "</p>";
    } else {
        echo "<p class='warning'>⚠ Não foi possível obter commit (git não disponível ou não é repositório git)</p>";
    }
} else {
    echo "<p class='warning'>⚠ Diretório .git não encontrado em: " . htmlspecialchars($gitPath) . "</p>";
}

// Verificar se código de logging existe
$dashboardControllerPath = dirname(__DIR__, 2) . '/app/Controllers/DashboardController.php';
$routerPath = dirname(__DIR__, 2) . '/app/Core/Router.php';

echo "<h3>Verificação de Assinaturas de Log:</h3>";

if (file_exists($dashboardControllerPath)) {
    $dashboardContent = file_get_contents($dashboardControllerPath);
    if (strpos($dashboardContent, '[DashboardController::dashboardInstrutor]') !== false) {
        echo "<p class='success'>✓ <code>DashboardController.php</code> contém logging de dashboardInstrutor</p>";
    } else {
        echo "<p class='error'>✗ <code>DashboardController.php</code> NÃO contém logging de dashboardInstrutor</p>";
        echo "<p class='warning'>⚠ O código com logging pode não estar em produção!</p>";
    }
} else {
    echo "<p class='error'>✗ Arquivo não encontrado: " . htmlspecialchars($dashboardControllerPath) . "</p>";
}

if (file_exists($routerPath)) {
    $routerContent = file_get_contents($routerPath);
    if (strpos($routerContent, '[Router]') !== false && strpos($routerContent, 'SQLSTATE') !== false) {
        echo "<p class='success'>✓ <code>Router.php</code> contém logging detalhado</p>";
    } else {
        echo "<p class='error'>✗ <code>Router.php</code> NÃO contém logging detalhado</p>";
    }
} else {
    echo "<p class='error'>✗ Arquivo não encontrado: " . htmlspecialchars($routerPath) . "</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>2. Localização do error_log</h2>";

$errorLogPath = ini_get('error_log');
$logErrors = ini_get('log_errors');

echo "<p><strong>error_log configurado:</strong> " . ($errorLogPath ? htmlspecialchars($errorLogPath) : '<span class="warning">(não configurado)</span>') . "</p>";
echo "<p><strong>log_errors:</strong> " . ($logErrors ? '<span class="success">ON</span>' : '<span class="error">OFF</span>') . "</p>";

// Tentar encontrar logs comuns
$possibleLogPaths = [
    dirname(__DIR__) . '/error_log',
    dirname(__DIR__, 2) . '/error_log',
    dirname(__DIR__, 2) . '/logs/error.log',
    dirname(__DIR__, 2) . '/logs/php_error.log',
    '/home/u502697186/domains/cfcbomconselho.com.br/public_html/painel/error_log',
    '/home/u502697186/domains/cfcbomconselho.com.br/logs/error.log',
];

echo "<h3>Possíveis localizações de log:</h3>";
echo "<ul>";
foreach ($possibleLogPaths as $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        $modified = date('Y-m-d H:i:s', filemtime($path));
        echo "<li class='success'>✓ <code>" . htmlspecialchars($path) . "</code> (tamanho: " . number_format($size) . " bytes, modificado: $modified)</li>";
    } else {
        echo "<li class='warning'>✗ <code>" . htmlspecialchars($path) . "</code> (não existe)</li>";
    }
}
echo "</ul>";

echo "</div>";

echo "<div class='section'>";
echo "<h2>3. Tentativa de Reprodução do Erro</h2>";

// Verificar se usuário está logado
session_start();
$userId = $_SESSION['user_id'] ?? null;
$currentRole = $_SESSION['current_role'] ?? null;
$userType = $_SESSION['user_type'] ?? null;

if (!$userId) {
    echo "<p class='warning'>⚠ Você precisa estar logado como instrutor para reproduzir o erro.</p>";
    echo "<p><a href='/login.php?type=instrutor'>Fazer login como instrutor</a></p>";
} else {
    echo "<p class='success'>✓ Usuário logado: ID $userId</p>";
    echo "<p><strong>current_role:</strong> " . ($currentRole ?? 'N/A') . "</p>";
    echo "<p><strong>user_type:</strong> " . ($userType ?? 'N/A') . "</p>";
    
    if ($currentRole !== 'INSTRUTOR' && $userType !== 'instrutor') {
        echo "<p class='warning'>⚠ Usuário não é instrutor. Faça login como instrutor primeiro.</p>";
    } else {
        echo "<p class='info'>ℹ️ Para reproduzir o erro:</p>";
        echo "<ol>";
        echo "<li>Abra uma nova aba e acesse: <a href='/dashboard' target='_blank'><code>/dashboard</code></a></li>";
        echo "<li>Imediatamente após, volte aqui e clique em 'Atualizar Logs' abaixo</li>";
        echo "</ol>";
        
        // Tentar ler logs recentes
        echo "<h3>Últimas linhas dos logs (filtrando por erro de dashboard):</h3>";
        
        $foundLogs = false;
        foreach ($possibleLogPaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                $foundLogs = true;
                echo "<h4>Log: <code>" . htmlspecialchars($logPath) . "</code></h4>";
                
                // Ler últimas 500 linhas
                $lines = file($logPath);
                $recentLines = array_slice($lines, -500);
                
                // Filtrar linhas relevantes
                $relevantLines = [];
                foreach ($recentLines as $line) {
                    if (stripos($line, 'DashboardController::dashboardInstrutor') !== false ||
                        stripos($line, '[Router]') !== false ||
                        stripos($line, 'SQLSTATE') !== false ||
                        stripos($line, 'PDOException') !== false ||
                        stripos($line, 'instructors') !== false ||
                        stripos($line, 'instrutores') !== false ||
                        stripos($line, 'Unknown table') !== false ||
                        stripos($line, 'Unknown column') !== false ||
                        stripos($line, 'Base table or view not found') !== false) {
                        $relevantLines[] = $line;
                    }
                }
                
                if (!empty($relevantLines)) {
                    echo "<pre>";
                    echo htmlspecialchars(implode('', array_slice($relevantLines, -50))); // Últimas 50 linhas relevantes
                    echo "</pre>";
                } else {
                    echo "<p class='info'>Nenhuma linha relevante encontrada nas últimas 500 linhas.</p>";
                    echo "<p class='info'>Tente acessar /dashboard agora e depois atualize esta página.</p>";
                }
                
                break; // Usar apenas o primeiro log encontrado
            }
        }
        
        if (!$foundLogs) {
            echo "<p class='error'>✗ Nenhum arquivo de log encontrado ou acessível.</p>";
            echo "<p class='info'>Execute no servidor via SSH:</p>";
            echo "<pre>";
            echo "tail -n 200 " . ($errorLogPath ?: '/caminho/para/error_log') . " | grep -i 'DashboardController\\|Router\\|SQLSTATE\\|PDOException\\|instructors'";
            echo "</pre>";
        }
    }
}

echo "</div>";

echo "<div class='section'>";
echo "<h2>4. Comandos para Executar no Servidor (SSH)</h2>";
echo "<p class='info'>Se você tiver acesso SSH, execute estes comandos:</p>";
echo "<pre>";
echo "# 1. Verificar commit atual\n";
echo "cd /home/u502697186/domains/cfcbomconselho.com.br/public_html/painel\n";
echo "git rev-parse --short HEAD\n";
echo "git log -1 --oneline\n\n";

echo "# 2. Verificar se código de logging existe\n";
echo "grep -n 'DashboardController::dashboardInstrutor' app/Controllers/DashboardController.php\n";
echo "grep -n '\\[Router\\].*SQLSTATE' app/Core/Router.php\n\n";

echo "# 3. Encontrar error_log\n";
echo "php -r \"echo ini_get('error_log');\"\n";
echo "ls -lah error_log 2>/dev/null || ls -lah ../error_log 2>/dev/null || ls -lah logs/error.log 2>/dev/null\n\n";

echo "# 4. Monitorar log em tempo real (execute ANTES de acessar /dashboard)\n";
echo "tail -f error_log | grep -i 'DashboardController\\|Router\\|SQLSTATE\\|PDOException'\n";
echo "</pre>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>5. Informações de Sessão Atual</h2>";
echo "<pre>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'N/A') . "\n";
echo "current_role: " . ($_SESSION['current_role'] ?? 'N/A') . "\n";
echo "user_type: " . ($_SESSION['user_type'] ?? 'N/A') . "\n";
echo "user_email: " . ($_SESSION['user_email'] ?? 'N/A') . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "</pre>";
echo "</div>";

?>

        <div class="section">
            <h2>6. Próximos Passos</h2>
            <ol>
                <li><strong>Verifique se o código está em produção</strong> (seção 1 acima)</li>
                <li><strong>Identifique onde está o error_log</strong> (seção 2 acima)</li>
                <li><strong>Faça login como instrutor</strong> e acesse <code>/dashboard</code></li>
                <li><strong>Copie o trecho do log</strong> que contém:
                    <ul>
                        <li>SQLSTATE[...]</li>
                        <li>Mensagem que cita tabela/coluna</li>
                        <li>5-15 linhas do stack trace</li>
                    </ul>
                </li>
                <li><strong>Envie essas informações</strong> para aplicar a correção cirúrgica</li>
            </ol>
        </div>

    </div>
</body>
</html>
