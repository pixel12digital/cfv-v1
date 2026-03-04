<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;
use App\Models\TheoryClass;
use App\Models\TheoryAttendance;
use App\Models\Student;

/**
 * Relatório de Presença/Faltas da Teoria (ADMIN/SECRETARIA).
 * Acompanha presença e faltas discriminando matéria/aula teórica.
 */
class RelatorioPresencaTeoricaController extends Controller
{
    private $cfcId;
    private $db;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->db = Database::getInstance()->getConnection();
    }

    public function index()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado. Apenas administradores e secretárias podem acessar o Relatório de Presença Teórica.';
            return;
        }

        $classModel = new TheoryClass();

        // Filtros
        $classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
        $studentId = isset($_GET['student_id']) && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : null;
        $disciplineId = isset($_GET['discipline_id']) && $_GET['discipline_id'] !== '' ? (int)$_GET['discipline_id'] : null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        // Buscar todas as turmas para o seletor
        $allClasses = $classModel->findByCfc($this->cfcId);

        // Dados da turma selecionada
        $selectedClass = null;
        $students = [];
        $disciplines = [];
        $attendanceRecords = [];
        $totals = [
            'total_sessions' => 0,
            'total_presences' => 0,
            'total_absences' => 0,
            'total_justified' => 0,
            'total_makeup' => 0,
            'attendance_rate' => 0
        ];
        $studentTotals = [];

        if ($classId) {
            $selectedClass = $classModel->findWithDetails($classId);
            
            // Validar que a turma pertence ao CFC
            if ($selectedClass && $selectedClass['cfc_id'] == $this->cfcId) {
                // Buscar disciplinas da turma
                $disciplines = $this->getDisciplinesByClass($classId);
                
                // Buscar alunos matriculados
                $students = $this->getStudentsByClass($classId);
                
                // Buscar registros de presença
                $attendanceRecords = $this->getAttendanceRecords($classId, $studentId, $disciplineId, $startDate, $endDate);
                
                // Calcular totais por aluno
                $studentTotals = $this->calculateStudentTotals($classId, $studentId, $startDate, $endDate);
                
                // Calcular totais gerais
                $totals = $this->calculateGeneralTotals($attendanceRecords, $studentTotals);
            } else {
                $selectedClass = null;
            }
        }

        $pageTitle = 'Relatório de Presença Teórica';
        $this->view('relatorio/presenca-teorica', [
            'pageTitle' => $pageTitle,
            'allClasses' => $allClasses,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'disciplines' => $disciplines,
            'attendanceRecords' => $attendanceRecords,
            'studentTotals' => $studentTotals,
            'totals' => $totals,
            'classId' => $classId,
            'studentId' => $studentId,
            'disciplineId' => $disciplineId,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    /**
     * Busca disciplinas de uma turma
     */
    private function getDisciplinesByClass($classId)
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT td.id, td.name
             FROM theory_sessions ts
             INNER JOIN theory_disciplines td ON ts.discipline_id = td.id
             WHERE ts.class_id = ?
             ORDER BY td.name ASC"
        );
        $stmt->execute([$classId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca alunos matriculados na turma
     */
    private function getStudentsByClass($classId)
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT s.id, COALESCE(s.full_name, s.name) as name
             FROM students s
             INNER JOIN theory_enrollments te ON s.id = te.student_id
             WHERE te.class_id = ? AND te.status = 'active'
             ORDER BY s.name ASC"
        );
        $stmt->execute([$classId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca registros de presença com filtros
     */
    private function getAttendanceRecords($classId, $studentId = null, $disciplineId = null, $startDate = null, $endDate = null)
    {
        $sql = "SELECT 
                    ta.id,
                    ta.student_id,
                    ta.status,
                    ta.notes,
                    ta.marked_at,
                    COALESCE(s.full_name, s.name) as student_name,
                    s.cpf as student_cpf,
                    td.name as discipline_name,
                    ts.starts_at,
                    ts.ends_at,
                    ts.duration_minutes,
                    u.nome as marked_by_name
                FROM theory_attendance ta
                INNER JOIN theory_sessions ts ON ta.session_id = ts.id
                INNER JOIN theory_disciplines td ON ts.discipline_id = td.id
                INNER JOIN students s ON ta.student_id = s.id
                LEFT JOIN usuarios u ON ta.marked_by = u.id
                WHERE ts.class_id = ? AND ts.status = 'done'";
        
        $params = [$classId];
        
        if ($studentId) {
            $sql .= " AND ta.student_id = ?";
            $params[] = $studentId;
        }
        
        if ($disciplineId) {
            $sql .= " AND ts.discipline_id = ?";
            $params[] = $disciplineId;
        }
        
        if ($startDate) {
            $sql .= " AND DATE(ts.starts_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(ts.starts_at) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY ts.starts_at DESC, s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Calcula totais por aluno
     */
    private function calculateStudentTotals($classId, $studentId = null, $startDate = null, $endDate = null)
    {
        $sql = "SELECT 
                    s.id as student_id,
                    COALESCE(s.full_name, s.name) as student_name,
                    COUNT(DISTINCT ts.id) as total_sessions,
                    SUM(CASE WHEN ta.status = 'present' THEN 1 ELSE 0 END) as presences,
                    SUM(CASE WHEN ta.status = 'absent' THEN 1 ELSE 0 END) as absences,
                    SUM(CASE WHEN ta.status = 'justified' THEN 1 ELSE 0 END) as justified,
                    SUM(CASE WHEN ta.status = 'makeup' THEN 1 ELSE 0 END) as makeup
                FROM students s
                INNER JOIN theory_enrollments te ON s.id = te.student_id
                INNER JOIN theory_sessions ts ON te.class_id = ts.class_id
                LEFT JOIN theory_attendance ta ON ts.id = ta.session_id AND s.id = ta.student_id
                WHERE te.class_id = ? AND te.status = 'active' AND ts.status = 'done'";
        
        $params = [$classId];
        
        if ($studentId) {
            $sql .= " AND s.id = ?";
            $params[] = $studentId;
        }
        
        if ($startDate) {
            $sql .= " AND DATE(ts.starts_at) >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND DATE(ts.starts_at) <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " GROUP BY s.id, s.name ORDER BY s.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Calcular percentual de presença
        foreach ($results as &$result) {
            $total = (int)$result['total_sessions'];
            $presences = (int)$result['presences'];
            $result['attendance_rate'] = $total > 0 ? round(($presences / $total) * 100, 1) : 0;
        }
        
        return $results;
    }

    /**
     * Calcula totais gerais
     */
    private function calculateGeneralTotals($attendanceRecords, $studentTotals)
    {
        $totals = [
            'total_sessions' => 0,
            'total_presences' => 0,
            'total_absences' => 0,
            'total_justified' => 0,
            'total_makeup' => 0,
            'attendance_rate' => 0
        ];
        
        foreach ($attendanceRecords as $record) {
            switch ($record['status']) {
                case 'present':
                    $totals['total_presences']++;
                    break;
                case 'absent':
                    $totals['total_absences']++;
                    break;
                case 'justified':
                    $totals['total_justified']++;
                    break;
                case 'makeup':
                    $totals['total_makeup']++;
                    break;
            }
        }
        
        // Calcular total de sessões únicas
        if (!empty($studentTotals)) {
            $totals['total_sessions'] = (int)$studentTotals[0]['total_sessions'];
        }
        
        // Calcular taxa média de presença
        if (!empty($studentTotals)) {
            $totalRate = 0;
            foreach ($studentTotals as $st) {
                $totalRate += (float)$st['attendance_rate'];
            }
            $totals['attendance_rate'] = count($studentTotals) > 0 
                ? round($totalRate / count($studentTotals), 1) 
                : 0;
        }
        
        return $totals;
    }

    /**
     * Exporta relatório para impressão
     */
    public function exportar()
    {
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;
        if (!in_array($role, ['ADMIN', 'SECRETARIA'], true)) {
            http_response_code(403);
            echo 'Acesso negado.';
            return;
        }

        // Mesmos filtros do index
        $classModel = new TheoryClass();
        $classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
        $studentId = isset($_GET['student_id']) && $_GET['student_id'] !== '' ? (int)$_GET['student_id'] : null;
        $disciplineId = isset($_GET['discipline_id']) && $_GET['discipline_id'] !== '' ? (int)$_GET['discipline_id'] : null;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $allClasses = $classModel->findByCfc($this->cfcId);
        $selectedClass = null;
        $students = [];
        $disciplines = [];
        $attendanceRecords = [];
        $totals = [];
        $studentTotals = [];

        if ($classId) {
            $selectedClass = $classModel->findWithDetails($classId);
            
            if ($selectedClass && $selectedClass['cfc_id'] == $this->cfcId) {
                $disciplines = $this->getDisciplinesByClass($classId);
                $students = $this->getStudentsByClass($classId);
                $attendanceRecords = $this->getAttendanceRecords($classId, $studentId, $disciplineId, $startDate, $endDate);
                $studentTotals = $this->calculateStudentTotals($classId, $studentId, $startDate, $endDate);
                $totals = $this->calculateGeneralTotals($attendanceRecords, $studentTotals);
            } else {
                $selectedClass = null;
            }
        }

        $pageTitle = 'Relatório de Presença Teórica';
        $this->view('relatorio/presenca-teorica', [
            'pageTitle' => $pageTitle,
            'allClasses' => $allClasses,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'disciplines' => $disciplines,
            'attendanceRecords' => $attendanceRecords,
            'studentTotals' => $studentTotals,
            'totals' => $totals,
            'classId' => $classId,
            'studentId' => $studentId,
            'disciplineId' => $disciplineId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'printMode' => true
        ]);
    }
}
