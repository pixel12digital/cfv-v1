<?php
/**
 * API: Relatório de Alunos por Status com Controle de Aulas
 * Endpoint para buscar dados de alunos com informações de aulas contratadas, realizadas e restantes
 * Acesso: ADMIN e SECRETARIA
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$user = getCurrentUser();
$userType = $user['tipo'] ?? null;

// Apenas ADMIN e SECRETARIA podem acessar
if (!in_array($userType, ['admin', 'secretaria'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Filtros
    $status = $_GET['status'] ?? '';
    $cfcId = $_GET['cfc_id'] ?? '';
    $dataInicio = $_GET['data_inicio'] ?? '';
    $dataFim = $_GET['data_fim'] ?? '';
    
    // Query base
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
            
            -- Contagem de aulas realizadas
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND l.enrollment_id = e.id 
             AND l.status = 'concluida'
            ) AS aulas_realizadas,
            
            -- Contagem de aulas agendadas (futuras ou hoje)
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND l.enrollment_id = e.id 
             AND l.status IN ('agendada', 'em_andamento')
             AND (l.scheduled_date > CURDATE() OR (l.scheduled_date = CURDATE() AND l.scheduled_time >= CURTIME()))
            ) AS aulas_agendadas,
            
            -- Contagem de aulas canceladas
            (SELECT COUNT(*) 
             FROM lessons l 
             WHERE l.student_id = s.id 
             AND l.enrollment_id = e.id 
             AND l.status IN ('cancelada', 'no_show')
            ) AS aulas_canceladas,
            
            -- Total de aulas contratadas via quotas (se usar sistema de quotas)
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
    
    // Aplicar filtros
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
    
    // Processar dados e calcular aulas restantes
    $resultado = [];
    foreach ($alunos as $aluno) {
        // Determinar total de aulas contratadas
        // Se usa sistema de quotas (total_quotas > 0), usar quotas
        // Senão, usar aulas_contratadas (pode ser NULL = sem limite)
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
        
        // Calcular aulas restantes
        // Se totalContratado é NULL, significa sem limite
        if ($totalContratado === null) {
            $aulasRestantes = null; // Sem limite
            $percentualConclusao = 0;
        } else {
            $aulasRestantes = $totalContratado - $aulasRealizadas - $aulasAgendadas;
            if ($aulasRestantes < 0) $aulasRestantes = 0;
            
            // Percentual de conclusão
            $percentualConclusao = $totalContratado > 0 
                ? round(($aulasRealizadas / $totalContratado) * 100, 1) 
                : 0;
        }
        
        // Verificar bloqueio financeiro
        $bloqueado = ($aluno['financial_status'] === 'bloqueado');
        
        // Montar resultado
        $resultado[] = [
            'id' => $aluno['id'],
            'nome' => $aluno['aluno_nome'],
            'cpf' => $aluno['cpf'],
            'telefone' => $aluno['phone'],
            'email' => $aluno['email'],
            'status' => $aluno['aluno_status'],
            'cfc_nome' => $aluno['cfc_nome'],
            'enrollment_id' => $aluno['enrollment_id'],
            'servico' => $aluno['service_name'],
            'enrollment_status' => $aluno['enrollment_status'],
            'financial_status' => $aluno['financial_status'],
            'bloqueado' => $bloqueado,
            'data_cadastro' => $aluno['data_cadastro'],
            'data_matricula' => $aluno['data_matricula'],
            'aulas_contratadas' => $totalContratado,
            'aulas_realizadas' => $aulasRealizadas,
            'aulas_agendadas' => $aulasAgendadas,
            'aulas_canceladas' => $aulasCanceladas,
            'aulas_restantes' => $aulasRestantes,
            'percentual_conclusao' => $percentualConclusao,
            'usa_quotas' => $totalQuotas > 0
        ];
    }
    
    // Calcular estatísticas gerais
    $stats = [
        'total_alunos' => count($resultado),
        'em_andamento' => count(array_filter($resultado, fn($a) => $a['status'] === 'em_andamento')),
        'concluido' => count(array_filter($resultado, fn($a) => $a['status'] === 'concluido')),
        'matriculado' => count(array_filter($resultado, fn($a) => $a['status'] === 'matriculado')),
        'cancelado' => count(array_filter($resultado, fn($a) => $a['status'] === 'cancelado')),
        'bloqueados' => count(array_filter($resultado, fn($a) => $a['bloqueado'])),
        'total_aulas_realizadas' => array_sum(array_column($resultado, 'aulas_realizadas')),
        'total_aulas_agendadas' => array_sum(array_column($resultado, 'aulas_agendadas'))
    ];
    
    echo json_encode([
        'success' => true,
        'alunos' => $resultado,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
