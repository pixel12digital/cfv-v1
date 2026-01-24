<?php
/**
 * Script de teste para validar fluxo de login de instrutor
 * Acesse via: http://localhost/cfc-v.1/public_html/tools/test_login_instrutor.php
 */

// Detectar caminho correto para includes
$rootPath = dirname(__DIR__, 2); // Sobe 2 níveis de public_html/tools para raiz
$includesPath = $rootPath . DIRECTORY_SEPARATOR . 'includes';

// Verificar se o caminho existe
if (!is_dir($includesPath)) {
    die("ERRO: Diretório includes não encontrado em: {$includesPath}<br>Verifique a estrutura de diretórios.");
}

require_once $includesPath . DIRECTORY_SEPARATOR . 'config.php';
require_once $includesPath . DIRECTORY_SEPARATOR . 'database.php';
require_once $includesPath . DIRECTORY_SEPARATOR . 'auth.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Login - Instrutor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .test-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007bff; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 10px 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Login - Instrutor</h1>

<?php
// Credenciais de teste
$email = 'rwavieira@gmail.com';
$senha = 'instrutor123';

echo "<div class='test-section'>";
echo "<h2>1. Verificando usuário no banco de dados</h2>";

try {
    $db = db();
    
    // Primeiro, buscar apenas por email (sem filtro de tipo)
    $usuarioPorEmail = $db->fetch("SELECT * FROM usuarios WHERE email = ?", [$email]);
    
    if ($usuarioPorEmail) {
        echo "<p class='info'>ℹ Usuário encontrado por email (sem filtro de tipo):</p>";
        echo "<pre>";
        echo "ID: {$usuarioPorEmail['id']}\n";
        echo "Nome: {$usuarioPorEmail['nome']}\n";
        echo "Email: {$usuarioPorEmail['email']}\n";
        echo "Tipo: " . ($usuarioPorEmail['tipo'] ?? 'N/A') . "\n";
        echo "Status: " . ($usuarioPorEmail['ativo'] ?? 'N/A') . "\n";
        echo "</pre>";
        
        // Se o tipo não for instrutor, avisar
        if (strtolower($usuarioPorEmail['tipo'] ?? '') !== 'instrutor') {
            echo "<p class='warning'>⚠ AVISO: O tipo do usuário é '{$usuarioPorEmail['tipo']}', não 'instrutor'</p>";
            echo "<p class='info'>Vamos usar este usuário mesmo assim para o teste...</p>";
            $usuario = $usuarioPorEmail;
        } else {
            $usuario = $usuarioPorEmail;
        }
    } else {
        // Tentar buscar por qualquer variação
        echo "<p class='error'>✗ Usuário não encontrado por email: {$email}</p>";
        echo "<p class='info'>Buscando todos os instrutores no banco...</p>";
        
        $todosInstrutores = $db->fetchAll("SELECT id, nome, email, tipo, ativo FROM usuarios WHERE tipo = 'instrutor' LIMIT 10");
        
        if (!empty($todosInstrutores)) {
            echo "<p class='info'>Instrutores encontrados no banco:</p>";
            echo "<pre>";
            foreach ($todosInstrutores as $instr) {
                echo "ID: {$instr['id']} | Nome: {$instr['nome']} | Email: {$instr['email']} | Ativo: " . ($instr['ativo'] ?? 'N/A') . "\n";
            }
            echo "</pre>";
            echo "<p class='warning'>⚠ Use um dos emails acima ou verifique se o email está correto</p>";
        } else {
            echo "<p class='error'>✗ Nenhum instrutor encontrado no banco de dados!</p>";
        }
        
        echo "</div></div></body></html>";
        exit;
    }
    
    if (!$usuario) {
        echo "<p class='error'>✗ ERRO: Não foi possível obter dados do usuário!</p>";
        echo "</div></div></body></html>";
        exit;
    }
    
    echo "<p class='success'>✓ Usuário encontrado e será usado para o teste:</p>";
    echo "<pre>";
    echo "ID: {$usuario['id']}\n";
    echo "Nome: {$usuario['nome']}\n";
    echo "Email: {$usuario['email']}\n";
    echo "Tipo: {$usuario['tipo']}\n";
    echo "Status: " . ($usuario['ativo'] ?? 'N/A') . "\n";
    echo "</pre>";
    
    // Verificar se está ativo
    if (isset($usuario['ativo']) && $usuario['ativo'] != 1) {
        echo "<p class='warning'>⚠ AVISO: Usuário está INATIVO no banco de dados</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ ERRO ao buscar usuário: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div></div></body></html>";
    exit;
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>2. Verificando senha</h2>";

if (!password_verify($senha, $usuario['senha'])) {
    echo "<p class='error'>✗ ERRO: Senha inválida!</p>";
    echo "</div></div></body></html>";
    exit;
}

echo "<p class='success'>✓ Senha válida</p>";
echo "</div>";

echo "<div class='test-section'>";
echo "<h2>3. Simulando login</h2>";

// Limpar sessão anterior
session_start();
session_destroy();
session_start();

try {
    $auth = new Auth();
    $result = $auth->login($email, $senha, false);
    
    if (!$result['success']) {
        echo "<p class='error'>✗ ERRO no login: " . htmlspecialchars($result['message']) . "</p>";
        echo "</div></div></body></html>";
        exit;
    }
    
    echo "<p class='success'>✓ Login bem-sucedido</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ ERRO ao fazer login: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div></div></body></html>";
    exit;
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>4. Verificando variáveis de sessão</h2>";

$sessionVars = [
    'user_id' => $_SESSION['user_id'] ?? 'NÃO DEFINIDO',
    'user_email' => $_SESSION['user_email'] ?? 'NÃO DEFINIDO',
    'user_type' => $_SESSION['user_type'] ?? 'NÃO DEFINIDO',
    'user_tipo' => $_SESSION['user_tipo'] ?? 'NÃO DEFINIDO',
    'current_role' => $_SESSION['current_role'] ?? 'NÃO DEFINIDO',
    'user_name' => $_SESSION['user_name'] ?? 'NÃO DEFINIDO',
];

echo "<pre>";
foreach ($sessionVars as $key => $value) {
    $status = ($value !== 'NÃO DEFINIDO') ? '✓' : '✗';
    $class = ($value !== 'NÃO DEFINIDO') ? 'success' : 'error';
    echo "<span class='{$class}'>{$status} {$key}: " . htmlspecialchars($value) . "</span>\n";
}
echo "</pre>";

// Verificar se current_role está definido
if (isset($_SESSION['current_role'])) {
    echo "<p class='success'>✓ current_role está definido corretamente</p>";
} else {
    echo "<p class='warning'>⚠ AVISO: current_role NÃO está definido</p>";
    echo "<p class='info'>O DashboardController precisará usar user_type/user_tipo como fallback</p>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>5. Verificando redirecionamento esperado</h2>";

$user = getCurrentUser();
if (!$user) {
    echo "<p class='error'>✗ ERRO: getCurrentUser() retornou null</p>";
} else {
    $tipo = strtolower($user['tipo'] ?? '');
    $expectedRedirect = '';
    
    switch ($tipo) {
        case 'instrutor':
            $expectedRedirect = '/instrutor/dashboard.php';
            break;
        case 'admin':
        case 'secretaria':
            $expectedRedirect = '/admin/index.php';
            break;
        case 'aluno':
            $expectedRedirect = '/aluno/dashboard.php';
            break;
        default:
            $expectedRedirect = '/login.php';
    }
    
    echo "<p><strong>Tipo do usuário:</strong> {$tipo}</p>";
    echo "<p><strong>Redirecionamento esperado:</strong> <code>{$expectedRedirect}</code></p>";
    
    if ($tipo === 'instrutor') {
        echo "<p class='success'>✓ Instrutor será redirecionado para o dashboard legado</p>";
    }
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>6. Testando conexões ao banco</h2>";

try {
    // Verificar quantas conexões estão sendo criadas
    $db1 = db();
    $db2 = db();
    
    // Verificar se são a mesma instância (singleton funcionando)
    if ($db1 === $db2) {
        echo "<p class='success'>✓ Singleton funcionando corretamente (mesma instância)</p>";
    } else {
        echo "<p class='error'>✗ ERRO: Singleton não está funcionando (instâncias diferentes)</p>";
    }
    
    // Testar uma query simples
    $testQuery = $db1->fetch("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'instrutor'");
    echo "<p class='success'>✓ Query de teste executada com sucesso</p>";
    echo "<p class='info'>Total de instrutores no banco: " . ($testQuery['total'] ?? 0) . "</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>✗ ERRO ao testar conexão: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>📋 Resumo do Teste</h2>";

$allOk = true;
$warnings = [];

if (!isset($_SESSION['user_id'])) {
    $allOk = false;
    echo "<p class='error'>✗ user_id não está na sessão</p>";
} else {
    echo "<p class='success'>✓ Login funcionando (user_id presente)</p>";
}

if (!isset($_SESSION['user_type']) && !isset($_SESSION['user_tipo'])) {
    $allOk = false;
    echo "<p class='error'>✗ user_type/user_tipo não está na sessão</p>";
} else {
    echo "<p class='success'>✓ Tipo do usuário na sessão</p>";
}

if (!isset($_SESSION['current_role'])) {
    $warnings[] = "current_role não definido (mas isso é esperado se usar sistema antigo)";
    echo "<p class='warning'>⚠ current_role não definido</p>";
} else {
    echo "<p class='success'>✓ current_role definido: {$_SESSION['current_role']}</p>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>🚀 Próximos Passos</h2>";
echo "<ol>";
echo "<li><strong>Teste manual no navegador:</strong><br>";
echo "   - Acesse: <a href='../login.php' target='_blank'>login.php</a><br>";
echo "   - Faça login com: <code>{$email}</code> / <code>{$senha}</code><br>";
echo "   - Verifique se é redirecionado para: <code>{$expectedRedirect}</code><br>";
echo "   - Verifique se NÃO aparece erro de conexão ao banco</li>";
echo "<li><strong>Se ainda houver erro de conexão:</strong><br>";
echo "   - Verifique os logs do PHP (error_log)<br>";
echo "   - Verifique se há múltiplas conexões sendo criadas<br>";
echo "   - Verifique se o singleton está funcionando corretamente</li>";
echo "</ol>";
echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='../login.php' class='btn'>Ir para Login</a>";
echo "<a href='../instrutor/dashboard.php' class='btn'>Ir para Dashboard Instrutor</a>";
echo "</div>";

echo "</div></body></html>";
?>
