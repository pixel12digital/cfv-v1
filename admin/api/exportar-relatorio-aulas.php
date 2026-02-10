<?php
/**
 * API para exportar Relatório de Aulas por período (CSV)
 * Mesmos filtros da página relatorio-aulas. Acesso: apenas ADMIN e SECRETARIA.
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não autorizado. Faça login.';
    exit;
}

$user = getCurrentUser();
$userType = $user['tipo'] ?? null;
if (!in_array($userType, ['admin', 'secretaria'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso negado. Apenas administradores e secretárias podem exportar o relatório de aulas.';
    exit;
}

$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$instrutorId = isset($_GET['instrutor_id']) && $_GET['instrutor_id'] !== '' ? (int)$_GET['instrutor_id'] : null;
$alunoId = isset($_GET['aluno_id']) && $_GET['aluno_id'] !== '' ? (int)$_GET['aluno_id'] : null;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

try {
    $db = db();
    $sql = "
        SELECT a.id, a.data_aula, a.hora_inicio, a.hora_fim,
               al.nome as aluno_nome,
               COALESCE(u.nome, i.nome) as instrutor_nome,
               a.tipo_aula,
               a.status
        FROM aulas a
        LEFT JOIN alunos al ON a.aluno_id = al.id
        LEFT JOIN instrutores i ON a.instrutor_id = i.id
        LEFT JOIN usuarios u ON i.usuario_id = u.id
        WHERE a.data_aula BETWEEN ? AND ?
    ";
    $params = [$dataInicio, $dataFim];
    if ($instrutorId !== null) {
        $sql .= " AND a.instrutor_id = ?";
        $params[] = $instrutorId;
    }
    if ($alunoId !== null) {
        $sql .= " AND a.aluno_id = ?";
        $params[] = $alunoId;
    }
    if ($status !== null) {
        $sql .= " AND a.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY a.data_aula ASC, a.hora_inicio ASC";
    $rows = $db->fetchAll($sql, $params);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro ao gerar relatório: ' . $e->getMessage();
    exit;
}

$filename = 'relatorio_aulas_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8

fputcsv($out, ['Relatório de Aulas por Período'], ';');
fputcsv($out, ['Período: ' . date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim))], ';');
fputcsv($out, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
fputcsv($out, [], ';');
fputcsv($out, ['Data', 'Horário', 'Aluno', 'Instrutor', 'Tipo', 'Status'], ';');

foreach ($rows as $a) {
    $statusLabel = ($a['status'] ?? '') === 'concluida' ? 'Realizada' : ucfirst(str_replace('_', ' ', $a['status'] ?? ''));
    fputcsv($out, [
        date('d/m/Y', strtotime($a['data_aula'])),
        date('H:i', strtotime($a['hora_inicio'])) . ' - ' . date('H:i', strtotime($a['hora_fim'])),
        $a['aluno_nome'] ?? '',
        $a['instrutor_nome'] ?? '',
        ucfirst($a['tipo_aula'] ?? ''),
        $statusLabel
    ], ';');
}

fclose($out);
exit;
