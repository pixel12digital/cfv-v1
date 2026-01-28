<?php

namespace App\Controllers;

use App\Models\Student;
use App\Models\Enrollment;
use App\Models\User;
use App\Config\Constants;
use App\Config\Database;
use App\Services\InstallmentsViewService;

class FinanceiroController extends Controller
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
        $currentRole = $_SESSION['current_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        $studentModel = new Student();
        $enrollmentModel = new Enrollment();
        $userModel = new User();
        
        $student = null;
        $enrollments = [];
        $totalDebt = 0;
        $totalPaid = 0;
        $overdueStudents = [];
        $dueSoonStudents = [];
        $recentStudents = [];
        $students = [];
        $search = '';
        $pendingEnrollments = [];
        $pendingTotal = 0;
        $pendingPage = 1;
        $pendingPerPage = 10;
        $allInstallments = [];
        $installmentsByEnrollment = [];
        
        // Se for ALUNO, carregar automaticamente os dados do próprio aluno
        if ($currentRole === Constants::ROLE_ALUNO && $userId) {
            $user = $userModel->findWithLinks($userId);
            if ($user && !empty($user['student_id'])) {
                $studentId = $user['student_id'];
                $student = $studentModel->find($studentId);
                if ($student && $student['cfc_id'] == $this->cfcId) {
                    $enrollments = $enrollmentModel->findByStudent($studentId);
                    
                    // Calcular totais (usando entry_amount para total pago e outstanding_amount para saldo)
                    // IMPORTANTE: Excluir matrículas canceladas do cálculo
                    foreach ($enrollments as $enr) {
                        // Pular matrículas canceladas
                        if ($enr['status'] === 'cancelada') {
                            continue;
                        }
                        
                        $finalPrice = (float)$enr['final_price'];
                        $entryAmount = (float)($enr['entry_amount'] ?? 0);
                        
                        // Usar outstanding_amount se disponível, senão calcular
                        if (isset($enr['outstanding_amount']) && $enr['outstanding_amount'] !== null) {
                            $outstanding = (float)$enr['outstanding_amount'];
                        } else {
                            $outstanding = max(0, $finalPrice - $entryAmount);
                        }
                        
                        $totalPaid += $entryAmount;
                        $totalDebt += $outstanding;
                    }
                    
                    // Calcular parcelas virtuais para o aluno
                    $installmentsService = new InstallmentsViewService();
                    $allInstallments = [];
                    $installmentsByEnrollment = [];
                    
                    foreach ($enrollments as $enr) {
                        if ($enr['status'] !== 'cancelada') {
                            $enrollmentInstallments = $installmentsService->getInstallmentsViewForEnrollment($enr);
                            $installmentsByEnrollment[$enr['id']] = $enrollmentInstallments;
                            $allInstallments = array_merge($allInstallments, $enrollmentInstallments);
                        }
                    }
                }
            }
        } else {
            // Comportamento administrativo (ADMIN, SECRETARIA, etc)
            $search = $_GET['q'] ?? '';
            $studentId = $_GET['student_id'] ?? null;
            $pendingPage = max(1, intval($_GET['page'] ?? 1));
            $filter = $_GET['filter'] ?? 'pending'; // 'pending', 'paid', 'all'
            
            if ($studentId) {
                $student = $studentModel->find($studentId);
                if ($student && $student['cfc_id'] == $this->cfcId) {
                    $enrollments = $enrollmentModel->findByStudent($studentId);
                    
                    // Registrar consulta recente
                    $this->recordRecentQuery($studentId);
                    
                    // Calcular totais (usando entry_amount para total pago e outstanding_amount para saldo)
                    // IMPORTANTE: Excluir matrículas canceladas do cálculo
                    foreach ($enrollments as $enr) {
                        // Pular matrículas canceladas
                        if ($enr['status'] === 'cancelada') {
                            continue;
                        }
                        
                        $finalPrice = (float)$enr['final_price'];
                        $entryAmount = (float)($enr['entry_amount'] ?? 0);
                        
                        // Usar outstanding_amount se disponível, senão calcular
                        if (isset($enr['outstanding_amount']) && $enr['outstanding_amount'] !== null) {
                            $outstanding = (float)$enr['outstanding_amount'];
                        } else {
                            $outstanding = max(0, $finalPrice - $entryAmount);
                        }
                        
                        $totalPaid += $entryAmount;
                        $totalDebt += $outstanding;
                    }
                }
            } elseif ($search) {
                // Buscar alunos OU filtrar pendentes
                // Se a busca retornar alunos, mostrar lista de alunos
                // Se não, aplicar filtro na lista de pendentes
                $students = $studentModel->findByCfc($this->cfcId, $search);
                
                // Se não encontrou alunos, aplicar filtro na lista conforme filtro selecionado
                if (empty($students)) {
                    $pendingResult = $this->getEnrollmentsByFilter($filter, $pendingPage, $pendingPerPage, $search);
                    $pendingEnrollments = $pendingResult['items'];
                    $pendingTotal = $pendingResult['total'];
                    $pendingSyncableCount = $pendingResult['syncable_count'] ?? 0;
                } else {
                    $pendingEnrollments = [];
                    $pendingTotal = 0;
                    $pendingSyncableCount = 0;
                }
            } else {
                $students = [];
                // Carregar lista conforme filtro selecionado
                $pendingResult = $this->getEnrollmentsByFilter($filter, $pendingPage, $pendingPerPage, '');
                $pendingEnrollments = $pendingResult['items'];
                $pendingTotal = $pendingResult['total'];
                $pendingSyncableCount = $pendingResult['syncable_count'] ?? 0;
            }
        }
        
        $data = [
            'pageTitle' => $currentRole === Constants::ROLE_ALUNO ? 'Financeiro' : 'Consulta Financeira',
            'student' => $student,
            'enrollments' => $enrollments,
            'totalDebt' => $totalDebt,
            'totalPaid' => $totalPaid,
            'search' => $search,
            'students' => $students ?? [],
            'overdueStudents' => $overdueStudents,
            'dueSoonStudents' => $dueSoonStudents,
            'recentStudents' => $recentStudents,
            'isAluno' => $currentRole === Constants::ROLE_ALUNO,
            'pendingEnrollments' => $pendingEnrollments ?? [],
            'pendingTotal' => $pendingTotal ?? 0,
            'pendingPage' => $pendingPage,
            'pendingPerPage' => $pendingPerPage,
            'pendingSyncableCount' => $pendingSyncableCount ?? 0,
            'installmentsByEnrollment' => $installmentsByEnrollment ?? [],
            'allInstallments' => $allInstallments ?? [],
            'filter' => $filter ?? 'pending'
        ];
        
        $this->view('financeiro/index', $data);
    }

    /**
     * Busca alunos em atraso (financial_status bloqueado ou pendente)
     */
    private function getOverdueStudents($limit = 10)
    {
        $sql = "SELECT s.id, s.name, s.cpf, s.full_name,
                SUM(CASE WHEN e.financial_status IN ('bloqueado', 'pendente') THEN e.final_price ELSE 0 END) as total_debt,
                MIN(COALESCE(
                    NULLIF(e.first_due_date, '0000-00-00'),
                    NULLIF(e.down_payment_due_date, '0000-00-00'),
                    DATE(e.created_at)
                )) as oldest_due_date
                FROM students s
                INNER JOIN enrollments e ON e.student_id = s.id
                WHERE s.cfc_id = ? 
                AND e.financial_status IN ('bloqueado', 'pendente')
                AND e.status != 'cancelada'
                GROUP BY s.id, s.name, s.cpf, s.full_name
                HAVING total_debt > 0
                ORDER BY oldest_due_date ASC, total_debt DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->cfcId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Busca alunos com vencimentos próximos (7 dias)
     */
    private function getDueSoonStudents($days = 7, $limit = 10)
    {
        $today = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime("+{$days} days"));
        
        $sql = "SELECT s.id, s.name, s.cpf, s.full_name,
                MIN(LEAST(
                    COALESCE(NULLIF(e.first_due_date, '0000-00-00'), '9999-12-31'),
                    COALESCE(NULLIF(e.down_payment_due_date, '0000-00-00'), '9999-12-31')
                )) as next_due_date,
                SUM(e.final_price) as total_debt
                FROM students s
                INNER JOIN enrollments e ON e.student_id = s.id
                WHERE s.cfc_id = ?
                AND e.status != 'cancelada'
                AND (
                    (e.first_due_date >= ? AND e.first_due_date <= ? AND e.first_due_date != '0000-00-00')
                    OR (e.down_payment_due_date >= ? AND e.down_payment_due_date <= ? AND e.down_payment_due_date != '0000-00-00')
                )
                GROUP BY s.id, s.name, s.cpf, s.full_name
                HAVING next_due_date != '9999-12-31'
                ORDER BY next_due_date ASC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->cfcId, $today, $endDate, $today, $endDate, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Busca alunos consultados recentemente pelo usuário
     */
    private function getRecentStudentsByUser($limit = 10)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return [];
        }

        // Verificar se a tabela existe
        try {
            $sql = "SELECT s.id, s.name, s.cpf, s.full_name, rq.last_viewed_at
                    FROM user_recent_financial_queries rq
                    INNER JOIN students s ON s.id = rq.student_id
                    WHERE rq.user_id = ? AND s.cfc_id = ?
                    ORDER BY rq.last_viewed_at DESC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $this->cfcId, $limit]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Tabela não existe ainda, retornar array vazio
            return [];
        }
    }

    /**
     * Registra consulta recente de um aluno
     */
    private function recordRecentQuery($studentId)
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return;
        }

        // Verificar se a tabela existe antes de tentar inserir
        try {
            // Usar INSERT ... ON DUPLICATE KEY UPDATE para atualizar ou inserir
            $sql = "INSERT INTO user_recent_financial_queries (user_id, student_id, last_viewed_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE last_viewed_at = NOW()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $studentId]);
        } catch (\PDOException $e) {
            // Tabela não existe ainda, ignorar silenciosamente
        }
    }

    /**
     * Verifica se uma coluna existe na tabela
     */
    private function columnExists($table, $column)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Busca matrículas conforme filtro (pending/paid/all)
     * 
     * @param string $filter 'pending', 'paid', ou 'all'
     * @param int $page Página atual
     * @param int $perPage Itens por página
     * @param string $search Filtro por nome/CPF
     * @return array {items: [], total: int, syncable_count: int}
     */
    private function getEnrollmentsByFilter($filter = 'pending', $page = 1, $perPage = 10, $search = '')
    {
        $offset = ($page - 1) * $perPage;
        
        // Verificar se coluna outstanding_amount existe
        $hasOutstandingAmount = $this->columnExists('enrollments', 'outstanding_amount');
        
        // Construir WHERE clause conforme filtro
        if ($filter === 'paid') {
            // Matrículas pagas: outstanding_amount = 0 ou (final_price - entry_amount) = 0
            // IMPORTANTE: Excluir matrículas canceladas (status = 'cancelada')
            if ($hasOutstandingAmount) {
                $whereClause = "AND e.status != 'cancelada' AND (e.outstanding_amount = 0 OR (e.outstanding_amount IS NULL AND e.final_price <= COALESCE(e.entry_amount, 0)))";
            } else {
                $whereClause = "AND e.status != 'cancelada' AND (e.final_price <= COALESCE(e.entry_amount, 0))";
            }
        } elseif ($filter === 'all') {
            // Todas as matrículas (sem filtro de saldo, mas excluir canceladas da listagem de saldo devedor)
            $whereClause = "AND e.status != 'cancelada'";
        } else {
            // Padrão: matrículas com saldo devedor (pending)
            // IMPORTANTE: Excluir matrículas canceladas e com cobrança cancelada
            if ($hasOutstandingAmount) {
                $whereClause = "AND e.status != 'cancelada' AND (e.outstanding_amount > 0 OR (e.outstanding_amount IS NULL AND e.final_price > COALESCE(e.entry_amount, 0)))";
            } else {
                $whereClause = "AND e.status != 'cancelada' AND (e.final_price > COALESCE(e.entry_amount, 0))";
            }
        }
        
        // Verificar se colunas do gateway existem (migration 030)
        $hasGatewayColumns = $this->columnExists('enrollments', 'gateway_charge_id');
        
        $sql = "SELECT e.*, 
                       COALESCE(s.full_name, s.name) as student_name, 
                       s.full_name as student_full_name, 
                       s.cpf as student_cpf,
                       sv.name as service_name,
                       CASE 
                           WHEN " . ($hasOutstandingAmount ? "e.outstanding_amount" : "(e.final_price - COALESCE(e.entry_amount, 0))") . " > 0 
                           THEN " . ($hasOutstandingAmount ? "e.outstanding_amount" : "(e.final_price - COALESCE(e.entry_amount, 0))") . "
                           ELSE 0 
                       END as calculated_outstanding
                FROM enrollments e
                INNER JOIN students s ON s.id = e.student_id
                INNER JOIN services sv ON sv.id = e.service_id
                WHERE e.cfc_id = ?
                AND e.status != 'cancelada'
                {$whereClause}";
        
        $params = [$this->cfcId];
        
        // Filtro por busca (nome/CPF)
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $sql .= " AND (s.name LIKE ? OR s.full_name LIKE ? OR s.cpf LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Ordenação: vencidas primeiro, depois por vencimento mais próximo
        $sql .= " ORDER BY 
                    CASE 
                        WHEN COALESCE(
                            NULLIF(e.first_due_date, '0000-00-00'),
                            NULLIF(e.down_payment_due_date, '0000-00-00'),
                            '9999-12-31'
                        ) < CURDATE() THEN 0
                        ELSE 1
                    END ASC,
                    COALESCE(
                        NULLIF(e.first_due_date, '0000-00-00'),
                        NULLIF(e.down_payment_due_date, '0000-00-00'),
                        DATE(e.created_at)
                    ) ASC,
                    e.id ASC
                LIMIT ? OFFSET ?";
        
        $params[] = $perPage;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        // Contar total (mesma query sem LIMIT)
        $countSql = "SELECT COUNT(*) as total
                     FROM enrollments e
                     INNER JOIN students s ON s.id = e.student_id
                     WHERE e.cfc_id = ?
                     AND e.status != 'cancelada'
                     {$whereClause}";
        
        $countParams = [$this->cfcId];
        
        if (!empty($search)) {
            $searchTerm = "%{$search}%";
            $countSql .= " AND (s.name LIKE ? OR s.full_name LIKE ? OR s.cpf LIKE ?)";
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($countParams);
        $total = $countStmt->fetch()['total'];
        
        // Contar quantas têm cobrança gerada (sincronizáveis)
        $syncableCount = 0;
        if ($hasGatewayColumns) {
            $syncableSql = "SELECT COUNT(*) as total
                           FROM enrollments e
                           INNER JOIN students s ON s.id = e.student_id
                           WHERE e.cfc_id = ?
                           AND e.status != 'cancelada'
                           {$whereClause}
                           AND e.gateway_charge_id IS NOT NULL
                           AND e.gateway_charge_id != ''";
            
            $syncableParams = [$this->cfcId];
            
            if (!empty($search)) {
                $searchTerm = "%{$search}%";
                $syncableSql .= " AND (s.name LIKE ? OR s.full_name LIKE ? OR s.cpf LIKE ?)";
                $syncableParams[] = $searchTerm;
                $syncableParams[] = $searchTerm;
                $syncableParams[] = $searchTerm;
            }
            
            $syncableStmt = $this->db->prepare($syncableSql);
            $syncableStmt->execute($syncableParams);
            $syncableCount = (int)$syncableStmt->fetch()['total'];
        }
        
        return [
            'items' => $items,
            'total' => (int)$total,
            'syncable_count' => $syncableCount
        ];
    }

    /**
     * Endpoint de autocomplete para busca
     */
    public function autocomplete()
    {
        header('Content-Type: application/json');
        
        $query = $_GET['q'] ?? '';
        if (strlen($query) < 2) {
            echo json_encode([]);
            exit;
        }

        $studentModel = new Student();
        $searchTerm = "%{$query}%";
        
        $sql = "SELECT id, name, cpf, full_name 
                FROM students 
                WHERE cfc_id = ? 
                AND (name LIKE ? OR full_name LIKE ? OR cpf LIKE ?)
                ORDER BY COALESCE(full_name, name) ASC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->cfcId, $searchTerm, $searchTerm, $searchTerm]);
        $results = $stmt->fetchAll();
        
        $output = [];
        foreach ($results as $row) {
            $output[] = [
                'id' => $row['id'],
                'name' => $row['full_name'] ?: $row['name'],
                'cpf' => $row['cpf']
            ];
        }
        
        echo json_encode($output);
        exit;
    }
}
