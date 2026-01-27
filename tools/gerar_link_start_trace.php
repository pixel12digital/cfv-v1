<?php
/**
 * Gera link /start?token=... para um aluno (para teste Trace Pack).
 * Uso: php tools/gerar_link_start_trace.php [nome_parcial]
 *      php tools/gerar_link_start_trace.php Ana
 * Sem argumento: usa o primeiro aluno com user_id.
 */

$base = dirname(__DIR__);
require_once $base . '/app/autoload.php';

use App\Config\Database;
use App\Config\Env;
use App\Models\Student;
use App\Models\FirstAccessToken;

Env::load();

$nameFilter = $argc > 1 ? trim($argv[1]) : null;

try {
    $db = Database::getInstance()->getConnection();
    // Buscar aluno com user_id (tabela students tem user_id; se não existir, fallback em outra tabela)
    $sql = "SELECT s.id, s.name, s.full_name, s.email, s.user_id, s.cfc_id FROM students s WHERE s.user_id IS NOT NULL AND s.user_id > 0";
    $params = [];
    if ($nameFilter !== null && $nameFilter !== '') {
        $sql .= " AND (s.name LIKE ? OR s.full_name LIKE ?)";
        $params[] = '%' . $nameFilter . '%';
        $params[] = '%' . $nameFilter . '%';
    }
    $sql .= " ORDER BY s.id ASC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        // Fallback: primeiro aluno com email (pode não ter user_id ainda)
        $sql2 = "SELECT s.id, s.name, s.full_name, s.email, s.user_id, s.cfc_id FROM students s WHERE s.email IS NOT NULL AND s.email != '' ORDER BY s.id ASC LIMIT 1";
        $student = $db->query($sql2)->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            echo "Nenhum aluno encontrado.\n";
            exit(1);
        }
        if (empty($student['user_id']) || (int)$student['user_id'] <= 0) {
            echo "Aluno encontrado sem user_id. Use o painel para criar acesso (ou vincular usuário) e depois rodar este script.\n";
            echo "Aluno: id={$student['id']} " . ($student['full_name'] ?? $student['name']) . " email=" . ($student['email'] ?? '') . "\n";
            exit(1);
        }
    }

    $userId = (int) $student['user_id'];
    $firstAccess = new FirstAccessToken();
    $plainToken = $firstAccess->create($userId, 48);

    if (!$plainToken) {
        echo "Falha ao criar token.\n";
        exit(1);
    }

    $baseUrl = getenv('TRACE_BASE_URL') ?: 'http://localhost/cfc-v.1/public_html';
    $baseUrl = rtrim($baseUrl, '/');
    $url = $baseUrl . '/start?token=' . $plainToken;

    echo "URL para teste Trace Pack:\n";
    echo $url . "\n\n";
    echo "Aluno: id={$student['id']} " . ($student['full_name'] ?? $student['name']) . " user_id=$userId email=" . ($student['email'] ?? '') . "\n";
    echo "Use essa URL no WhatsApp in-app ou Chrome anônimo, defina senha, anote onde caiu e capture os logs.\n";
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    exit(1);
}
