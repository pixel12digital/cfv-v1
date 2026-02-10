<?php
/**
 * Diagnóstico 500 - Contas a Pagar
 * Rode no servidor: php admin/diagnostico-500-contas-a-pagar.php
 * Ou acesse via browser (só em produção para ver o erro): .../admin/diagnostico-500-contas-a-pagar.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$admin_dir = __DIR__;
require_once $admin_dir . '/../includes/config.php';
require_once $admin_dir . '/../includes/database.php';
require_once $admin_dir . '/../includes/auth.php';

echo "1. Includes OK\n";

$db = Database::getInstance();
echo "2. DB OK\n";

// Simular sessão de um admin (buscar primeiro admin do banco)
$admin = $db->fetch("SELECT id FROM usuarios WHERE tipo = 'admin' LIMIT 1");
if (!$admin) {
    $admin = $db->fetch("SELECT id FROM usuarios LIMIT 1");
}
if (!$admin) {
    die("3. ERRO: Nenhum usuário no banco para simular sessão.\n");
}
$_SESSION['user_id'] = $admin['id'];
$_SESSION['last_activity'] = time();
$_SESSION['active_role'] = 'ADMIN';
$_SESSION['current_role'] = 'ADMIN';
echo "3. Sessão simulada para user_id={$admin['id']}\n";

$user = getCurrentUser();
if (!$user) {
    die("4. ERRO: getCurrentUser() retornou null.\n");
}
echo "4. getCurrentUser OK: id={$user['id']} tipo=" . ($user['tipo'] ?? '?') . "\n";

$isAdmin = ($user['tipo'] ?? '') === 'admin';
$userType = $user['tipo'] ?? '';
if (!$isAdmin && $userType !== 'secretaria') {
    die("5. ERRO: usuário não é admin nem secretaria.\n");
}
echo "5. Permissão OK\n";

// Incluir a página (só o PHP, sem HTML do admin)
echo "6. Incluindo pages/financeiro-despesas.php ...\n";
ob_start();
try {
    include $admin_dir . '/pages/financeiro-despesas.php';
    $out = ob_get_clean();
    echo "7. Página incluída com sucesso. Saída: " . strlen($out) . " bytes.\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "7. ERRO CAPTURADO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
echo "Diagnóstico concluído sem erros.\n";
