<?php
/**
 * API: Exportar Relatório de Alunos por Status (CSV)
 * Acesso: ADMIN e SECRETARIA
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    die('Não autenticado');
}

$user = getCurrentUser();
$userType = $user['tipo'] ?? null;

// Apenas ADMIN e SECRETARIA podem acessar
if (!in_array($userType, ['admin', 'secretaria'], true)) {
    http_response_code(403);
    die('Acesso negado');
}

try {
    $db = Database::getInstance();
    
    // Filtros (mesmos do relatório)
    $status = $_GET['status'] ?? '';
    $cfcId = $_GET['cfc_id'] ?? '';
    $dataInicio = $_GET['data_inicio'] ?? '';
    $dataFim = $_GET['data_fim'] ?? '';
    
    // Query base (mesma do relatório)
    $sql = "
        SELECT 
            s.id,
            s.name AS aluno_nome,
            s.cpf,
            s.phone,
            s.email,
            s.status AS aluno_status,
            s.created_at AS data_cadastro,
            c.nome AS cfc_nome,
            e.id AS enrollment_id,
            e.service_name,
            e.status AS enrollment_status,
            e.financial_status,
            e.aulas_contratadas,
            e.created_at AS data_matricula,
            
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND l.enrollment_id = e.id 
             AND l.status = 'concluida'
            ) AS aulas_realizadas,
            
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND l.enrollment_id = e.id 
             AND l.status IN ('agendada', 'em_andamento')
             AND (l.scheduled_date > CURDATE() OR (l.scheduled_date = CURDATE() AND l.scheduled_time >= CURTIME()))
            ) AS aulas_agendadas,
            
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND l.enrollment_id = e.id 
             AND l.status IN ('cancelada', 'no_show')
            ) AS aulas_canceladas,
            
            (SELECT COALESCE(SUM(elq.quantity), 0)
             FROM enrollment_lesson_quotas elq
             WHERE elq.enrollment_id = e.id
            ) AS total_quotas
            
        FROM students s
        LEFT JOIN cfcs c ON s.cfc_id = c.id
        LEFT JOIN enrollments e ON e.student_id = s.id AND e.status = 'ativa' AND e.deleted_at IS NULL
        WHERE 1=1
    ";
    
    $params = [];
    
    if (!empty($status)) {
        $sql .= " AND s.status = ?";
        $params[] = $status;
    }
    
    if (!empty($cfcId)) {
        $sql .= " AND s.cfc_id = ?";
        $params[] = $cfcId;
    }
    
    if (!empty($dataInicio)) {
        $sql .= " AND e.created_at >= ?";
        $params[] = $dataInicio . ' 00:00:00';
    }
    
    if (!empty($dataFim)) {
        $sql .= " AND e.created_at <= ?";
        $params[] = $dataFim . ' 23:59:59';
    }
    
    $sql .= " ORDER BY s.name ASC";
    
    $alunos = $db->fetchAll($sql, $params);
    
    // Configurar headers para download CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio-alunos-status-' . date('Y-m-d-His') . '.csv"');
    
    // Abrir output
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (compatibilidade com Excel)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos
    fputcsv($output, [
        'ID',
        'Nome',
        'CPF',
        'Telefone',
        'Email',
        'Status',
        'CFC',
        'Serviço',
        'Status Matrícula',
        'Status Financeiro',
        'Data Cadastro',
        'Data Matrícula',
        'Aulas Contratadas',
        'Aulas Realizadas',
        'Aulas Agendadas',
        'Aulas Canceladas',
        'Aulas Restantes',
        'Percentual Conclusão'
    ], ';');
    
    // Dados
    foreach ($alunos as $aluno) {
        $totalQuotas = (int)($aluno['total_quotas'] ?? 0);
        $aulasContratadas = $aluno['aulas_contratadas'];
        
        if ($totalQuotas > 0) {
            $totalContratado = $totalQuotas;
        } else {
            $totalContratado = $aulasContratadas;
        }
        
        $aulasRealizadas = (int)($aluno['aulas_realizadas'] ?? 0);
        $aulasAgendadas = (int)($aluno['aulas_agendadas'] ?? 0);
        $aulasCanceladas = (int)($aluno['aulas_canceladas'] ?? 0);
        
        if ($totalContratado === null) {
            $aulasRestantes = 'Sem limite';
            $percentualConclusao = '0%';
        } else {
            $aulasRestantes = $totalContratado - $aulasRealizadas - $aulasAgendadas;
            if ($aulasRestantes < 0) $aulasRestantes = 0;
            $percentualConclusao = $totalContratado > 0 
                ? round(($aulasRealizadas / $totalContratado) * 100, 1) . '%'
                : '0%';
        }
        
        fputcsv($output, [
            $aluno['id'],
            $aluno['aluno_nome'],
            $aluno['cpf'],
            $aluno['phone'],
            $aluno['email'],
            ucfirst($aluno['aluno_status']),
            $aluno['cfc_nome'],
            $aluno['service_name'],
            ucfirst($aluno['enrollment_status'] ?? '-'),
            ucfirst($aluno['financial_status'] ?? '-'),
            $aluno['data_cadastro'] ? date('d/m/Y', strtotime($aluno['data_cadastro'])) : '-',
            $aluno['data_matricula'] ? date('d/m/Y', strtotime($aluno['data_matricula'])) : '-',
            $totalContratado ?? 'Sem limite',
            $aulasRealizadas,
            $aulasAgendadas,
            $aulasCanceladas,
            $aulasRestantes,
            $percentualConclusao
        ], ';');
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Erro ao exportar: ' . $e->getMessage());
}
