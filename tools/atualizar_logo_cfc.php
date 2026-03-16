<?php
/**
 * Script para atualizar o logo do CFC
 * Acesse via: /tools/atualizar_logo_cfc.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');

require_once APP_PATH . '/autoload.php';

use App\Config\Env;
use App\Config\Database;

Env::load();

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Atualizar Logo CFC</title>';
echo '<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}';
echo '.ok{color:green;}.err{color:red;}.info{color:#333;}</style></head><body>';
echo '<h2>Atualizar Logo CFC</h2>';

// Caminhos
$sourceFile   = ROOT_PATH . '/public_html/assets/logo-novo.png';
$uploadDir    = ROOT_PATH . '/storage/uploads/cfcs/';
$filename     = 'cfc_1_logo.png';
$destFile     = $uploadDir . $filename;
$relativePath = 'storage/uploads/cfcs/' . $filename;

echo "<p class='info'>ROOT_PATH: <code>" . ROOT_PATH . "</code></p>";
echo "<p class='info'>Fonte: <code>$sourceFile</code></p>";
echo "<p class='info'>Destino: <code>$destFile</code></p>";

// 1. Verificar arquivo fonte
if (!file_exists($sourceFile)) {
    echo "<p class='err'>ERRO: Arquivo fonte não encontrado: $sourceFile</p></body></html>";
    exit(1);
}
echo "<p class='ok'>✔ Arquivo fonte encontrado (" . round(filesize($sourceFile) / 1024, 1) . " KB)</p>";

// 2. Criar diretório se necessário
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo "<p class='err'>ERRO: Não foi possível criar diretório $uploadDir</p></body></html>";
        exit(1);
    }
    echo "<p class='ok'>✔ Diretório criado</p>";
} else {
    echo "<p class='ok'>✔ Diretório existe</p>";
}

// 3. Copiar novo logo
if (!copy($sourceFile, $destFile)) {
    echo "<p class='err'>ERRO: Falha ao copiar arquivo para $destFile</p></body></html>";
    exit(1);
}
echo "<p class='ok'>✔ Novo logo copiado para $relativePath</p>";

// 4. Atualizar banco de dados
try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->query("SELECT id, nome, logo_path FROM cfcs ORDER BY id ASC LIMIT 1");
    $cfc  = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cfc) {
        echo "<p class='err'>ERRO: Nenhum CFC encontrado no banco</p></body></html>";
        exit(1);
    }

    echo "<p class='info'>CFC: <strong>{$cfc['nome']}</strong> (ID: {$cfc['id']})</p>";
    echo "<p class='info'>logo_path anterior: <code>" . ($cfc['logo_path'] ?? 'NULL') . "</code></p>";

    // Remover arquivo físico antigo se for diferente
    if (!empty($cfc['logo_path']) && $cfc['logo_path'] !== $relativePath) {
        $oldFile = ROOT_PATH . '/' . $cfc['logo_path'];
        if (file_exists($oldFile)) {
            unlink($oldFile);
            echo "<p class='info'>ℹ Arquivo antigo removido: {$cfc['logo_path']}</p>";
        }
    }

    // Atualizar banco
    $update = $db->prepare("UPDATE cfcs SET logo_path = ?, updated_at = NOW() WHERE id = ?");
    $update->execute([$relativePath, $cfc['id']]);

    // Verificar
    $verify  = $db->prepare("SELECT logo_path FROM cfcs WHERE id = ?");
    $verify->execute([$cfc['id']]);
    $updated = $verify->fetch(PDO::FETCH_ASSOC);

    if ($updated['logo_path'] === $relativePath) {
        echo "<p class='ok'>✔ Banco atualizado: logo_path = <code>$relativePath</code></p>";
    } else {
        echo "<p class='err'>ERRO: Banco não foi atualizado. Valor atual: " . htmlspecialchars($updated['logo_path']) . "</p>";
        exit(1);
    }

} catch (Exception $e) {
    echo "<p class='err'>ERRO no banco: " . htmlspecialchars($e->getMessage()) . "</p></body></html>";
    exit(1);
}

echo "<hr><p class='ok'><strong>✔ Logo atualizado com sucesso!</strong></p>";
echo "<p><a href='/login'>Verificar no login &rarr;</a></p>";
echo "</body></html>";
