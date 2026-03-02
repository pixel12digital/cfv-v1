<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;
use App\Models\Lesson;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\Vehicle;

/**
 * Relatório de Quilometragem por período (ADMIN/SECRETARIA).
 * Consolida KM rodado por dia/semana/mês/ano com filtros.
 */
class RelatorioQuilometragemController extends Controller
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
            echo 'Acesso negado. Apenas administradores e secretárias podem acessar o Relatório de Quilometragem.';
            return;
        }

        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        $visao = $_GET['visao'] ?? 'diario'; // diario, semanal, mensal, anual
        $instrutorId = isset($_GET['instrutor_id']) && $_GET['instrutor_id'] !== '' ? (int) $_GET['instrutor_id'] : null;
        $veiculoId = isset($_GET['veiculo_id']) && $_GET['veiculo_id'] !== '' ? (int) $_GET['veiculo_id'] : null;
        $alunoId = isset($_GET['aluno_id']) && $_GET['aluno_id'] !== '' ? (int) $_GET['aluno_id'] : null;

        // Buscar aulas concluídas com km_start e km_end
        $list = $this->getKmData($dataInicio, $dataFim, $instrutorId, $veiculoId, $alunoId);

        // Consolidar por período conforme visão
        $consolidated = $this->consolidateByPeriod($list, $visao);

        // Calcular totais
        $totais = $this->calculateTotals($list);

        // Buscar listas para filtros
        $instrutores = [];
        $veiculos = [];
        $alunos = [];
        try {
            $instructorModel = new Instructor();
            $instrutores = $instructorModel->findByCfc($this->cfcId);
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $vehicleModel = new Vehicle();
            $veiculos = $vehicleModel->findByCfc($this->cfcId);
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $studentModel = new Student();
            $alunos = $studentModel->findByCfc($this->cfcId);
        } catch (\Throwable $e) {
            // ignore
        }

        $pageTitle = 'Relatório de Quilometragem';
        $this->view('relatorio/quilometragem', [
            'pageTitle' => $pageTitle,
            'list' => $list,
            'consolidated' => $consolidated,
            'totais' => $totais,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'visao' => $visao,
            'filtroInstrutor' => $instrutorId,
            'filtroVeiculo' => $veiculoId,
            'filtroAluno' => $alunoId,
            'instrutores' => $instrutores,
            'veiculos' => $veiculos,
            'alunos' => $alunos,
        ]);
    }

    /**
     * Exportar CSV
     */
    public function exportar()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        $visao = $_GET['visao'] ?? 'diario';
        $instrutorId = isset($_GET['instrutor_id']) && $_GET['instrutor_id'] !== '' ? (int) $_GET['instrutor_id'] : null;
        $veiculoId = isset($_GET['veiculo_id']) && $_GET['veiculo_id'] !== '' ? (int) $_GET['veiculo_id'] : null;
        $alunoId = isset($_GET['aluno_id']) && $_GET['aluno_id'] !== '' ? (int) $_GET['aluno_id'] : null;

        $list = $this->getKmData($dataInicio, $dataFim, $instrutorId, $veiculoId, $alunoId);
        $consolidated = $this->consolidateByPeriod($list, $visao);

        $filename = 'relatorio_quilometragem_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Relatório de Quilometragem'], ';');
        fputcsv($out, ['Período: ' . date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim))], ';');
        fputcsv($out, ['Visão: ' . ucfirst($visao)], ';');
        fputcsv($out, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
        fputcsv($out, [], ';');
        fputcsv($out, ['Período', 'KM Inicial', 'KM Final', 'KM Rodado', 'Aulas', 'Inconsistências'], ';');

        foreach ($consolidated as $row) {
            fputcsv($out, [
                $row['periodo_label'],
                number_format($row['km_inicial'], 0, ',', '.'),
                number_format($row['km_final'], 0, ',', '.'),
                number_format($row['km_rodado'], 0, ',', '.'),
                $row['total_aulas'],
                $row['inconsistencias'] > 0 ? $row['inconsistencias'] : '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Busca dados de KM das aulas concluídas
     */
    private function getKmData($dataInicio, $dataFim, $instrutorId, $veiculoId, $alunoId)
    {
        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT l.id,
                       l.scheduled_date,
                       l.km_start,
                       l.km_end,
                       l.instructor_id,
                       l.vehicle_id,
                       l.student_id,
                       COALESCE(s.full_name, s.name) as student_name,
                       i.name as instructor_name,
                       v.plate as vehicle_plate
                FROM lessons l
                INNER JOIN students s ON l.student_id = s.id
                LEFT JOIN instructors i ON l.instructor_id = i.id
                LEFT JOIN vehicles v ON l.vehicle_id = v.id
                WHERE l.cfc_id = ?
                  AND l.scheduled_date BETWEEN ? AND ?
                  AND l.status = 'concluida'
                  AND l.type = 'pratica'
                  AND l.km_start IS NOT NULL
                  AND l.km_end IS NOT NULL";
        
        $params = [$this->cfcId, $dataInicio, $dataFim];
        
        if ($instrutorId !== null) {
            $sql .= " AND l.instructor_id = ?";
            $params[] = $instrutorId;
        }
        
        if ($veiculoId !== null) {
            $sql .= " AND l.vehicle_id = ?";
            $params[] = $veiculoId;
        }
        
        if ($alunoId !== null) {
            $sql .= " AND l.student_id = ?";
            $params[] = $alunoId;
        }
        
        $sql .= " ORDER BY l.scheduled_date ASC, l.km_start ASC";
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Calcular km_rodado e detectar inconsistências
            foreach ($results as &$row) {
                $row['km_rodado'] = $row['km_end'] - $row['km_start'];
                $row['inconsistente'] = $row['km_end'] < $row['km_start'];
            }
            
            return $results;
        } catch (\PDOException $e) {
            error_log('[RelatorioQuilometragemController] Erro ao buscar dados: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Consolida dados por período (diário/semanal/mensal/anual)
     */
    private function consolidateByPeriod($list, $visao)
    {
        $consolidated = [];
        
        foreach ($list as $row) {
            $key = $this->getPeriodKey($row['scheduled_date'], $visao);
            
            if (!isset($consolidated[$key])) {
                $consolidated[$key] = [
                    'periodo' => $key,
                    'periodo_label' => $this->getPeriodLabel($row['scheduled_date'], $visao),
                    'km_inicial' => PHP_INT_MAX,
                    'km_final' => 0,
                    'km_rodado' => 0,
                    'total_aulas' => 0,
                    'inconsistencias' => 0,
                    'aulas' => []
                ];
            }
            
            $consolidated[$key]['km_inicial'] = min($consolidated[$key]['km_inicial'], $row['km_start']);
            $consolidated[$key]['km_final'] = max($consolidated[$key]['km_final'], $row['km_end']);
            $consolidated[$key]['km_rodado'] += $row['km_rodado'];
            $consolidated[$key]['total_aulas']++;
            if ($row['inconsistente']) {
                $consolidated[$key]['inconsistencias']++;
            }
            $consolidated[$key]['aulas'][] = $row;
        }
        
        return array_values($consolidated);
    }

    /**
     * Gera chave do período
     */
    private function getPeriodKey($date, $visao)
    {
        $timestamp = strtotime($date);
        switch ($visao) {
            case 'semanal':
                return date('Y-W', $timestamp); // Ano-Semana
            case 'mensal':
                return date('Y-m', $timestamp); // Ano-Mês
            case 'anual':
                return date('Y', $timestamp); // Ano
            case 'diario':
            default:
                return date('Y-m-d', $timestamp); // Dia
        }
    }

    /**
     * Gera label do período
     */
    private function getPeriodLabel($date, $visao)
    {
        $timestamp = strtotime($date);
        switch ($visao) {
            case 'semanal':
                $week = date('W', $timestamp);
                $year = date('Y', $timestamp);
                return "Semana {$week}/{$year}";
            case 'mensal':
                return ucfirst(strftime('%B/%Y', $timestamp));
            case 'anual':
                return date('Y', $timestamp);
            case 'diario':
            default:
                return date('d/m/Y', $timestamp);
        }
    }

    /**
     * Calcula totais gerais
     */
    private function calculateTotals($list)
    {
        $totais = [
            'total_aulas' => count($list),
            'km_total' => 0,
            'inconsistencias' => 0,
            'km_medio_por_aula' => 0
        ];
        
        foreach ($list as $row) {
            $totais['km_total'] += $row['km_rodado'];
            if ($row['inconsistente']) {
                $totais['inconsistencias']++;
            }
        }
        
        if ($totais['total_aulas'] > 0) {
            $totais['km_medio_por_aula'] = $totais['km_total'] / $totais['total_aulas'];
        }
        
        return $totais;
    }
}
