<?php
/**
 * Diagnóstico de conexão DB — mesmo fluxo do LEGADO (aluno/dashboard.php).
 * Usa apenas includes/config.php + includes/database.php. Não usa .env nem app.
 *
 * Uso: php tools/diagnostico_db_legado.php   [apenas CLI]
 * Ou via web (REMOVER após uso): ?token=DIAG_LEGADO_2026
 *
 * Segurança: permitir só em CLI ou, se web, exigir token e depois remover este arquivo.
 */

$allowWeb = false; // mudar para true só para um teste rápido; exigir token
$secretToken = 'DIAG_LEGADO_2026';

if (php_sapi_name() !== 'cli') {
    if (!$allowWeb || ($_GET['token'] ?? '') !== $secretToken) {
        http_response_code(403);
        die('Acesso negado. Execute via CLI: php tools/diagnostico_db_legado.php');
    }
}

// Simular docroot do legado (a partir da pasta do projeto = parent de tools/)
$base = dirname(__DIR__);
chdir($base);

// Carregar exatamente o que o legado carrega (sem auth para evitar redirect)
require_once $base . '/includes/config.php';

// Fonte das credenciais (sem senha)
echo "=== Diagnóstico DB LEGADO (mesmo include que aluno/dashboard.php) ===\n\n";
echo "Fonte: includes/config.php (constantes)\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'não definido') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'não definido') . "\n";
$user = defined('DB_USER') ? DB_USER : 'não definido';
echo "DB_USER: " . (strlen($user) > 4 ? substr($user, 0, 4) . '***' : $user) . "\n";
echo "DB_PASS: " . (defined('DB_PASS') && DB_PASS !== '' ? '***definida***' : 'não definida') . "\n";
echo "Ambiente (detectEnvironment): " . (isset($environment) ? $environment : 'N/A') . "\n\n";

// Agora carregar database e testar (mesmo path que auth.php → db())
require_once $base . '/includes/database.php';

try {
    $db = db();
    $row = $db->fetch("SELECT 1 AS ok, DATABASE() AS db_name, NOW() AS dt");
    echo "Conexão: OK\n";
    echo "SELECT 1: ok=" . ($row['ok'] ?? '') . ", DATABASE()=" . ($row['db_name'] ?? '') . ", NOW()=" . ($row['dt'] ?? '') . "\n";
} catch (Throwable $e) {
    echo "Conexão: FALHOU\n";
    echo "Classe: " . get_class($e) . "\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n=== Fim diagnóstico ===\n";
