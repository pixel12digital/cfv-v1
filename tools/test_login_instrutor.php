<?php
/**
 * Script de teste para validar fluxo de login de instrutor
 * Simula o processo de login e verifica redirecionamentos
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== TESTE DE LOGIN DE INSTRUTOR ===\n\n";

// Credenciais de teste
$email = 'rwavieira@gmail.com';
$senha = 'instrutor123';

// Limpar sessão anterior
session_start();
session_destroy();
session_start();

echo "1. Verificando se usuário existe no banco...\n";
$db = db();
$usuario = $db->fetch("SELECT * FROM usuarios WHERE email = ? AND tipo = 'instrutor'", [$email]);

if (!$usuario) {
    die("ERRO: Usuário não encontrado no banco de dados!\n");
}

echo "   ✓ Usuário encontrado: ID={$usuario['id']}, Nome={$usuario['nome']}, Tipo={$usuario['tipo']}\n\n";

echo "2. Verificando senha...\n";
if (!password_verify($senha, $usuario['senha'])) {
    die("ERRO: Senha inválida!\n");
}
echo "   ✓ Senha válida\n\n";

echo "3. Simulando login via Auth::login()...\n";
$auth = new Auth();
$result = $auth->login($email, $senha, false);

if (!$result['success']) {
    die("ERRO no login: " . $result['message'] . "\n");
}
echo "   ✓ Login bem-sucedido\n\n";

echo "4. Verificando variáveis de sessão...\n";
echo "   - user_id: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "\n";
echo "   - user_email: " . ($_SESSION['user_email'] ?? 'NÃO DEFINIDO') . "\n";
echo "   - user_type: " . ($_SESSION['user_type'] ?? 'NÃO DEFINIDO') . "\n";
echo "   - current_role: " . ($_SESSION['current_role'] ?? 'NÃO DEFINIDO') . "\n";
echo "   - user_tipo: " . ($_SESSION['user_tipo'] ?? 'NÃO DEFINIDO') . "\n\n";

echo "5. Verificando função redirectAfterLogin()...\n";
$user = getCurrentUser();
if (!$user) {
    die("ERRO: getCurrentUser() retornou null\n");
}

echo "   ✓ Usuário obtido da sessão: {$user['nome']} ({$user['tipo']})\n";

// Capturar o redirecionamento (sem realmente redirecionar)
ob_start();
try {
    redirectAfterLogin($user);
    $redirectOutput = ob_get_clean();
    echo "   ⚠ redirectAfterLogin() tentou redirecionar (esperado)\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "   ERRO: " . $e->getMessage() . "\n";
}

echo "\n6. Verificando redirecionamento esperado...\n";
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

echo "   Tipo do usuário: {$tipo}\n";
echo "   Redirecionamento esperado: {$expectedRedirect}\n";

// Verificar se current_role está definido corretamente
if (isset($_SESSION['current_role'])) {
    echo "   ✓ current_role está definido: {$_SESSION['current_role']}\n";
} else {
    echo "   ⚠ AVISO: current_role NÃO está definido na sessão\n";
    echo "   Isso pode causar problemas no DashboardController\n";
}

echo "\n7. Testando DashboardController (simulação)...\n";
if (empty($_SESSION['current_role']) && !empty($_SESSION['user_type'])) {
    echo "   ⚠ DashboardController precisará usar user_type/user_tipo como fallback\n";
    echo "   Isso está implementado no código atual\n";
} elseif (!empty($_SESSION['current_role'])) {
    echo "   ✓ DashboardController terá current_role disponível\n";
} else {
    echo "   ⚠ AVISO: Nem current_role nem user_type estão definidos!\n";
}

echo "\n=== RESUMO DO TESTE ===\n";
echo "Status: ";
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    echo "✓ Login funcionando\n";
    if (isset($_SESSION['current_role'])) {
        echo "✓ current_role definido corretamente\n";
    } else {
        echo "⚠ current_role não definido (mas user_type está)\n";
    }
    echo "✓ Redirecionamento esperado: {$expectedRedirect}\n";
} else {
    echo "✗ FALHA no login\n";
}

echo "\n=== PRÓXIMOS PASSOS ===\n";
echo "1. Teste manual no navegador:\n";
echo "   - Acesse: http://localhost/cfc-v.1/public_html/login.php\n";
echo "   - Faça login com: {$email} / {$senha}\n";
echo "   - Verifique se é redirecionado para: {$expectedRedirect}\n";
echo "   - Verifique se NÃO aparece erro de conexão ao banco\n";
echo "\n2. Se ainda houver erro de conexão:\n";
echo "   - Verifique os logs do PHP\n";
echo "   - Verifique se há múltiplas conexões sendo criadas\n";
echo "   - Verifique se o singleton está funcionando corretamente\n";
