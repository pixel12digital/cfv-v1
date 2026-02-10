<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;
use App\Models\Lesson;
use App\Models\Instructor;
use App\Models\Student;

/**
 * Relatório de Aulas por período (ADMIN/SECRETARIA).
 * Mesma base da agenda (Lesson::findByPeriodWithTheoryDedupe), filtros e totais.
 */
class RelatorioAulasController extends Controller
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
            echo 'Acesso negado. Apenas administradores e secretárias podem acessar o Relatório de Aulas.';
            return;
        }

        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
        $dataFim = $_GET['data_fim'] ?? date('Y-m-d');
        $instrutorId = isset($_GET['instrutor_id']) && $_GET['instrutor_id'] !== '' ? (int) $_GET['instrutor_id'] : null;
        $alunoId = isset($_GET['aluno_id']) && $_GET['aluno_id'] !== '' ? (int) $_GET['aluno_id'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        $filters = ['show_canceled' => true];
        if ($instrutorId !== null) {
            $filters['instructor_id'] = $instrutorId;
        }
        if ($status !== null) {
            $filters['status'] = $status;
        }

        $lessonModel = new Lesson();
        try {
            $list = $lessonModel->findByPeriodWithTheoryDedupe($this->cfcId, $dataInicio, $dataFim, $filters);
        } catch (\Throwable $e) {
            error_log('[RelatorioAulasController] Erro ao buscar aulas: ' . $e->getMessage());
            $list = [];
        }

        if ($alunoId !== null) {
            $list = array_values(array_filter($list, function ($row) use ($alunoId) {
                return isset($row['student_id']) && (int) $row['student_id'] === $alunoId;
            }));
        }

        $totais = ['total' => 0, 'agendadas' => 0, 'realizadas' => 0, 'canceladas' => 0, 'em_andamento' => 0];
        foreach ($list as $row) {
            $totais['total']++;
            $st = $this->normalizeStatus($row['status'] ?? '');
            if ($st === 'agendada') {
                $totais['agendadas']++;
            } elseif ($st === 'concluida') {
                $totais['realizadas']++;
            } elseif ($st === 'cancelada') {
                $totais['canceladas']++;
            } elseif ($st === 'em_andamento') {
                $totais['em_andamento']++;
            }
        }

        $instrutores = [];
        $alunos = [];
        try {
            $instructorModel = new Instructor();
            $instrutores = $instructorModel->findByCfc($this->cfcId);
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $studentModel = new Student();
            $alunos = $studentModel->findByCfc($this->cfcId);
        } catch (\Throwable $e) {
            // ignore
        }

        $pageTitle = 'Relatório de Aulas por Período';
        $this->view('relatorio/aulas', [
            'pageTitle' => $pageTitle,
            'list' => $list,
            'totais' => $totais,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'filtroInstrutor' => $instrutorId,
            'filtroAluno' => $alunoId,
            'filtroStatus' => $status,
            'instrutores' => $instrutores,
            'alunos' => $alunos,
        ]);
    }

    /**
     * Exportar CSV (mesmos filtros). Acesso: ADMIN/SECRETARIA.
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
        $instrutorId = isset($_GET['instrutor_id']) && $_GET['instrutor_id'] !== '' ? (int) $_GET['instrutor_id'] : null;
        $alunoId = isset($_GET['aluno_id']) && $_GET['aluno_id'] !== '' ? (int) $_GET['aluno_id'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        $filters = ['show_canceled' => true];
        if ($instrutorId !== null) {
            $filters['instructor_id'] = $instrutorId;
        }
        if ($status !== null) {
            $filters['status'] = $status;
        }

        $lessonModel = new Lesson();
        try {
            $list = $lessonModel->findByPeriodWithTheoryDedupe($this->cfcId, $dataInicio, $dataFim, $filters);
        } catch (\Throwable $e) {
            $list = [];
        }
        if ($alunoId !== null) {
            $list = array_values(array_filter($list, function ($row) use ($alunoId) {
                return isset($row['student_id']) && (int) $row['student_id'] === $alunoId;
            }));
        }

        $filename = 'relatorio_aulas_' . date('Y-m-d_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, ['Relatório de Aulas por Período'], ';');
        fputcsv($out, ['Período: ' . date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim))], ';');
        fputcsv($out, ['Gerado em: ' . date('d/m/Y H:i:s')], ';');
        fputcsv($out, [], ';');
        fputcsv($out, ['Data', 'Horário', 'Aluno', 'Instrutor', 'Tipo', 'Status'], ';');

        foreach ($list as $row) {
            $dataAula = $row['scheduled_date'] ?? '';
            $horaInicio = $row['scheduled_time'] ?? '';
            $dur = (int) ($row['duration_minutes'] ?? 50);
            $horaFim = $horaInicio ? date('H:i', strtotime($horaInicio) + $dur * 60) : '';
            $horario = $horaInicio && $horaFim ? (date('H:i', strtotime($horaInicio)) . ' - ' . $horaFim) : '';
            $alunoNome = $row['student_name'] ?? $row['student_names'] ?? '';
            $instrutorNome = $row['instructor_name'] ?? '';
            $tipo = isset($row['type']) ? ucfirst($row['type']) : '';
            $st = $this->normalizeStatus($row['status'] ?? '');
            $statusLabel = $st === 'concluida' ? 'Realizada' : ($st === 'em_andamento' ? 'Em andamento' : ucfirst(str_replace('_', ' ', $st)));
            fputcsv($out, [
                $dataAula ? date('d/m/Y', strtotime($dataAula)) : '',
                $horario,
                $alunoNome,
                $instrutorNome,
                $tipo,
                $statusLabel,
            ], ';');
        }
        fclose($out);
        exit;
    }

    private function normalizeStatus($status)
    {
        $s = strtolower((string) $status);
        if ($s === 'scheduled' || $s === 'agendada') {
            return 'agendada';
        }
        if ($s === 'done' || $s === 'concluida') {
            return 'concluida';
        }
        if ($s === 'canceled' || $s === 'cancelada') {
            return 'cancelada';
        }
        if ($s === 'in_progress' || $s === 'em_andamento') {
            return 'em_andamento';
        }
        return $s ?: 'agendada';
    }
}
