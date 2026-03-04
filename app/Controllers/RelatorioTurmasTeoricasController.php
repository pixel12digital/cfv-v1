<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\Constants;
use App\Models\TheoryClass;
use App\Models\TheoryEnrollment;

/**
 * Relatório de Turmas Teóricas (ADMIN/SECRETARIA).
 * Lista alunos por turma com totalizadores.
 */
class RelatorioTurmasTeoricasController extends Controller
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
            echo 'Acesso negado. Apenas administradores e secretárias podem acessar o Relatório de Turmas Teóricas.';
            return;
        }

        $classModel = new TheoryClass();
        $enrollmentModel = new TheoryEnrollment();

        // Filtros
        $classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
        $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        // Buscar todas as turmas para o seletor
        $allClasses = $classModel->findByCfc($this->cfcId, $statusFilter);

        // Dados da turma selecionada e alunos
        $selectedClass = null;
        $students = [];
        $totals = [
            'total_students' => 0,
            'active' => 0,
            'inactive' => 0,
            'completed' => 0
        ];

        if ($classId) {
            $selectedClass = $classModel->findWithDetails($classId);
            
            // Validar que a turma pertence ao CFC
            if ($selectedClass && $selectedClass['cfc_id'] == $this->cfcId) {
                $students = $enrollmentModel->findByClass($classId);
                
                // Calcular totais
                $totals['total_students'] = count($students);
                foreach ($students as $student) {
                    $status = $student['status'] ?? 'active';
                    if (isset($totals[$status])) {
                        $totals[$status]++;
                    }
                }
            } else {
                $selectedClass = null;
            }
        }

        $pageTitle = 'Relatório de Turmas Teóricas';
        $this->view('relatorio/turmas-teoricas', [
            'pageTitle' => $pageTitle,
            'allClasses' => $allClasses,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'totals' => $totals,
            'classId' => $classId,
            'statusFilter' => $statusFilter
        ]);
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

        $classModel = new TheoryClass();
        $enrollmentModel = new TheoryEnrollment();

        $classId = isset($_GET['class_id']) && $_GET['class_id'] !== '' ? (int)$_GET['class_id'] : null;
        $statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

        $allClasses = $classModel->findByCfc($this->cfcId, $statusFilter);
        $selectedClass = null;
        $students = [];
        $totals = [
            'total_students' => 0,
            'active' => 0,
            'inactive' => 0,
            'completed' => 0
        ];

        if ($classId) {
            $selectedClass = $classModel->findWithDetails($classId);
            
            if ($selectedClass && $selectedClass['cfc_id'] == $this->cfcId) {
                $students = $enrollmentModel->findByClass($classId);
                
                $totals['total_students'] = count($students);
                foreach ($students as $student) {
                    $status = $student['status'] ?? 'active';
                    if (isset($totals[$status])) {
                        $totals[$status]++;
                    }
                }
            } else {
                $selectedClass = null;
            }
        }

        $pageTitle = 'Relatório de Turmas Teóricas';
        $this->view('relatorio/turmas-teoricas', [
            'pageTitle' => $pageTitle,
            'allClasses' => $allClasses,
            'selectedClass' => $selectedClass,
            'students' => $students,
            'totals' => $totals,
            'classId' => $classId,
            'statusFilter' => $statusFilter,
            'printMode' => true
        ]);
    }
}
