<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;

/**
 * Relatório de Alunos por Status com Controle de Aulas (ADMIN/SECRETARIA)
 */
class RelatorioAlunosStatusController extends Controller
{
    private $cfcId;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
    }

    public function index()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado. Apenas administradores e secretárias podem acessar este relatório.';
            return;
        }

        $db = Database::getInstance()->getConnection();

        // Filtros
        $status = $_GET['status'] ?? '';
        $cfcIdFilter = $_GET['cfc_id'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';

        // Buscar CFCs para filtro
        $cfcs = [];
        try {
            $stmt = $db->prepare("SELECT id, nome FROM cfcs WHERE ativo = 1 ORDER BY nome");
            $stmt->execute();
            $cfcs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('[RelatorioAlunosStatus] Erro ao buscar CFCs: ' . $e->getMessage());
        }

        // Buscar dados dos alunos
        $alunos = [];
        $stats = [
            'total_alunos' => 0,
            'em_andamento' => 0,
            'concluido' => 0,
            'matriculado' => 0,
            'cancelado' => 0,
            'bloqueados' => 0,
            'total_aulas_realizadas' => 0,
            'total_aulas_agendadas' => 0
        ];

        try {
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

            if (!empty($cfcIdFilter)) {
                $sql .= " AND s.cfc_id = ?";
                $params[] = $cfcIdFilter;
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

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Processar dados
            foreach ($resultados as $aluno) {
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
                    $aulasRestantes = null;
                    $percentualConclusao = 0;
                } else {
                    $aulasRestantes = $totalContratado - $aulasRealizadas - $aulasAgendadas;
                    if ($aulasRestantes < 0) $aulasRestantes = 0;
                    $percentualConclusao = $totalContratado > 0 
                        ? round(($aulasRealizadas / $totalContratado) * 100, 1) 
                        : 0;
                }

                $bloqueado = ($aluno['financial_status'] === 'bloqueado');

                $alunos[] = [
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

                // Atualizar estatísticas
                $stats['total_alunos']++;
                if ($aluno['aluno_status'] === 'em_andamento') $stats['em_andamento']++;
                if ($aluno['aluno_status'] === 'concluido') $stats['concluido']++;
                if ($aluno['aluno_status'] === 'matriculado') $stats['matriculado']++;
                if ($aluno['aluno_status'] === 'cancelado') $stats['cancelado']++;
                if ($bloqueado) $stats['bloqueados']++;
                $stats['total_aulas_realizadas'] += $aulasRealizadas;
                $stats['total_aulas_agendadas'] += $aulasAgendadas;
            }

        } catch (\Exception $e) {
            error_log('[RelatorioAlunosStatus] Erro ao buscar dados: ' . $e->getMessage());
        }

        $pageTitle = 'Relatório de Alunos por Status';
        $this->view('relatorio/alunos-status', [
            'pageTitle' => $pageTitle,
            'alunos' => $alunos,
            'stats' => $stats,
            'cfcs' => $cfcs,
            'filtroStatus' => $status,
            'filtroCfc' => $cfcIdFilter,
            'filtroDataInicio' => $dataInicio,
            'filtroDataFim' => $dataFim
        ]);
    }

    public function exportar()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            die('Acesso negado');
        }

        $db = Database::getInstance()->getConnection();

        // Mesmos filtros do relatório
        $status = $_GET['status'] ?? '';
        $cfcIdFilter = $_GET['cfc_id'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';

        try {
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
                    
                    (SELECT COUNT(*) FROM lessons l WHERE l.student_id = s.id AND l.enrollment_id = e.id AND l.status = 'concluida') AS aulas_realizadas,
                    (SELECT COUNT(*) FROM lessons l WHERE l.student_id = s.id AND l.enrollment_id = e.id AND l.status IN ('agendada', 'em_andamento') AND (l.scheduled_date > CURDATE() OR (l.scheduled_date = CURDATE() AND l.scheduled_time >= CURTIME()))) AS aulas_agendadas,
                    (SELECT COUNT(*) FROM lessons l WHERE l.student_id = s.id AND l.enrollment_id = e.id AND l.status IN ('cancelada', 'no_show')) AS aulas_canceladas,
                    (SELECT COALESCE(SUM(elq.quantity), 0) FROM enrollment_lesson_quotas elq WHERE elq.enrollment_id = e.id) AS total_quotas
                    
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
            if (!empty($cfcIdFilter)) {
                $sql .= " AND s.cfc_id = ?";
                $params[] = $cfcIdFilter;
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

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $alunos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Headers CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="relatorio-alunos-status-' . date('Y-m-d-His') . '.csv"');

            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'ID', 'Nome', 'CPF', 'Telefone', 'Email', 'Status', 'CFC', 'Serviço',
                'Status Matrícula', 'Status Financeiro', 'Data Cadastro', 'Data Matrícula',
                'Aulas Contratadas', 'Aulas Realizadas', 'Aulas Agendadas', 'Aulas Canceladas',
                'Aulas Restantes', 'Percentual Conclusão'
            ], ';');

            foreach ($alunos as $aluno) {
                $totalQuotas = (int)($aluno['total_quotas'] ?? 0);
                $aulasContratadas = $aluno['aulas_contratadas'];
                $totalContratado = $totalQuotas > 0 ? $totalQuotas : $aulasContratadas;
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

        } catch (\Exception $e) {
            http_response_code(500);
            die('Erro ao exportar: ' . $e->getMessage());
        }
    }
}
