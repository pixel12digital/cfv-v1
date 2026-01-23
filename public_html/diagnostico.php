<?php
/**
 * Script de Diagnóstico Simples - Dashboard HTTP 500
 * Acesse: https://painel.cfcbomconselho.com.br/diagnostico.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico Dashboard - HTTP 500</h1>";
echo "<pre>";

echo "=== INFORMAÇÕES BÁSICAS ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "Caminho atual: " . __DIR__ . "\n";
echo "\n";

echo "=== VERIFICAÇÃO DE ARQUIVOS ===\n";
$files = [
    'index.php' => __DIR__ . '/index.php',
    'Bootstrap.php' => __DIR__ . '/../app/Bootstrap.php',
    'DashboardController.php' => __DIR__ . '/../app/Controllers/DashboardController.php',
];

foreach ($files as $name => $path) {
    echo $name . ": " . (file_exists($path) ? "EXISTE" : "NÃO EXISTE") . " (" . $path . ")\n";
}
echo "\n";

echo "=== TESTE DE SESSÃO ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Sessão: " . (session_status() === PHP_SESSION_ACTIVE ? "ATIVA" : "INATIVA") . "\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NÃO DEFINIDO') . "\n";
echo "\n";

echo "=== TESTE DE CARREGAMENTO ===\n";
try {
    define('ROOT_PATH', dirname(__DIR__));
    define('APP_PATH', ROOT_PATH . '/app');
    
    if (file_exists(APP_PATH . '/autoload.php')) {
        require_once APP_PATH . '/autoload.php';
        echo "✓ Autoload carregado\n";
    }
    
    if (file_exists(APP_PATH . '/Bootstrap.php')) {
        require_once APP_PATH . '/Bootstrap.php';
        echo "✓ Bootstrap carregado\n";
    }
    
    if (class_exists('App\\Controllers\\DashboardController')) {
        echo "✓ DashboardController encontrado\n";
    } else {
        echo "✗ DashboardController NÃO encontrado\n";
    }
} catch (Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='/dashboard'>Tentar acessar /dashboard</a></p>";
