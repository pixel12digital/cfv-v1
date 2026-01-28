<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Step;
use App\Models\StudentStep;
use App\Models\RescheduleRequest;
use App\Models\Notification;
use App\Config\Constants;
use App\Config\Database;
use App\Services\InstallmentsViewService;

class DashboardController extends Controller
{
    public function index()
    {
        // Proteção contra loop de redirecionamento
        $redirectCount = $_SESSION['redirect_count'] ?? 0;
        if ($redirectCount > 3) {
            // Limpar contador e mostrar erro
            unset($_SESSION['redirect_count']);
            error_log('[DashboardController] Loop de redirecionamento detectado. Interrompendo.');
            die('Erro: Loop de redirecionamento detectado. Por favor, limpe os cookies e tente novamente.');
        }
        
        try {
            $currentRole = $_SESSION['current_role'] ?? '';
            $userId = $_SESSION['user_id'] ?? null;
            
            // Se não houver user_id, redirecionar para login
            if (!$userId) {
                $_SESSION['redirect_count'] = ($redirectCount ?? 0) + 1;
                $this->redirectToLogin();
                return;
            }
            
            // Resetar contador se chegou aqui com user_id válido
            unset($_SESSION['redirect_count']);
            
            // Se não houver current_role mas houver user_id, verificar tipo do usuário (sistema antigo)
            if (empty($currentRole) && $userId) {
                try {
                    // CORREÇÃO: Verificar primeiro se há user_type na sessão (sistema antigo)
                    $userType = $_SESSION['user_type'] ?? $_SESSION['user_tipo'] ?? null;
                    
                    if ($userType) {
                        $tipo = strtolower($userType);
                        if ($tipo === 'aluno') {
                            unset($_SESSION['redirect_count']);
                            return $this->dashboardAluno($userId);
                        }
                        $_SESSION['redirect_count'] = ($redirectCount ?? 0) + 1;
                        $this->redirectToLegacyDashboard($tipo);
                        return;
                    }
                    
                    // Se não houver user_type, buscar do banco (último recurso)
                    $userModel = new User();
                    $user = $userModel->find($userId);
                    
                    if ($user) {
                        $tipo = strtolower($user['tipo'] ?? '');
                        if ($tipo === 'aluno') {
                            unset($_SESSION['redirect_count']);
                            return $this->dashboardAluno($userId);
                        }
                        $_SESSION['redirect_count'] = ($redirectCount ?? 0) + 1;
                        $this->redirectToLegacyDashboard($tipo);
                        return;
                    } else {
                        // Usuário não encontrado - limpar sessão e redirecionar para login
                        session_destroy();
                        session_start();
                        $this->redirectToLogin();
                        return;
                    }
                } catch (\PDOException $e) {
                    error_log('[DashboardController] Erro de conexão ao banco: ' . $e->getMessage());
                    
                    // Se for erro de limite de conexões, mostrar mensagem específica
                    if (strpos($e->getMessage(), 'max_connections_per_hour') !== false || 
                        strpos($e->getMessage(), 'Limite de conexões') !== false) {
                        $errorMessage = 'Limite de conexões ao banco de dados excedido. Por favor, aguarde alguns minutos e tente novamente.';
                    } else {
                        $errorMessage = 'Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.';
                    }
                    
                    $data = [
                        'pageTitle' => 'Erro de Conexão',
                        'error' => $errorMessage
                    ];
                    if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                        $this->view('errors/500', $data);
                    } else {
                        die('Erro: ' . htmlspecialchars($errorMessage) . ' <a href="' . base_url('logout') . '">Fazer logout</a>');
                    }
                    return;
                } catch (\Exception $e) {
                    error_log('[DashboardController] Erro ao verificar tipo do usuário: ' . $e->getMessage());
                    // Se houver erro ao buscar usuário, não redirecionar para login para evitar loop
                    // Tentar usar dashboard genérico ou mostrar erro
                    $data = [
                        'pageTitle' => 'Erro',
                        'error' => 'Erro ao carregar dados do usuário. Por favor, faça logout e login novamente.'
                    ];
                    if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                        $this->view('errors/500', $data);
                    } else {
                        die('Erro ao carregar dados do usuário. <a href="' . base_url('logout') . '">Fazer logout</a>');
                    }
                    return;
                }
            }
            
            // Aluno: renderizar dashboard do app (evita depender do legado /aluno/dashboard.php)
            if ($currentRole === Constants::ROLE_ALUNO && $userId) {
                return $this->dashboardAluno($userId);
            }
            
            // Se for INSTRUTOR, carregar dados específicos
            if ($currentRole === Constants::ROLE_INSTRUTOR && $userId) {
                return $this->dashboardInstrutor($userId);
            }
            
            // Se for ADMIN ou SECRETARIA, carregar dashboard administrativo
            if (($currentRole === Constants::ROLE_ADMIN || $currentRole === Constants::ROLE_SECRETARIA) && $userId) {
                return $this->dashboardAdmin($userId);
            }
            
            // Se chegou aqui sem role mas tem user_id, não redirecionar para login (evitar loop)
            // Tentar usar dashboard genérico ou mostrar mensagem
            if ($userId && empty($currentRole)) {
                error_log('[DashboardController] Usuário sem current_role: user_id=' . $userId);
                // Tentar buscar usuário novamente para redirecionar para dashboard legado
                try {
                    $userModel = new User();
                    $user = $userModel->find($userId);
                    if ($user) {
                        $tipo = strtolower($user['tipo'] ?? '');
                        if ($tipo === 'aluno') {
                            return $this->dashboardAluno($userId);
                        }
                        $this->redirectToLegacyDashboard($tipo);
                        return;
                    }
                } catch (\PDOException $e) {
                    error_log('[DashboardController] Erro de conexão ao banco ao buscar usuário: ' . $e->getMessage());
                    
                    // Se for erro de limite de conexões, mostrar mensagem específica
                    if (strpos($e->getMessage(), 'max_connections_per_hour') !== false || 
                        strpos($e->getMessage(), 'Limite de conexões') !== false) {
                        $errorMessage = 'Limite de conexões ao banco de dados excedido. Por favor, aguarde alguns minutos e tente novamente.';
                    } else {
                        $errorMessage = 'Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.';
                    }
                    
                    $data = [
                        'pageTitle' => 'Erro de Conexão',
                        'error' => $errorMessage
                    ];
                    if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                        $this->view('errors/500', $data);
                    } else {
                        die('Erro: ' . htmlspecialchars($errorMessage) . ' <a href="' . base_url('logout') . '">Fazer logout</a>');
                    }
                    return;
                } catch (\Exception $e) {
                    error_log('[DashboardController] Erro ao buscar usuário: ' . $e->getMessage());
                }
                
                // Se não conseguir redirecionar, mostrar erro ao invés de redirecionar para login
                $data = [
                    'pageTitle' => 'Erro de Configuração',
                    'error' => 'Não foi possível determinar seu tipo de usuário. Por favor, faça logout e login novamente.'
                ];
                if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                    $this->view('errors/500', $data);
                } else {
                    die('Erro: Não foi possível determinar seu tipo de usuário. <a href="' . base_url('logout') . '">Fazer logout</a>');
                }
                return;
            }
            
            // Dashboard genérico para outros perfis
            $data = [
                'pageTitle' => 'Dashboard'
            ];
            $this->view('dashboard', $data);
        } catch (\PDOException $e) {
            error_log('[DashboardController] Erro de conexão ao banco: ' . $e->getMessage());
            
            // Se for erro de limite de conexões, mostrar mensagem específica
            if (strpos($e->getMessage(), 'max_connections_per_hour') !== false || 
                strpos($e->getMessage(), 'Limite de conexões') !== false) {
                $errorMessage = 'Limite de conexões ao banco de dados excedido. Por favor, aguarde alguns minutos e tente novamente.';
            } else {
                $errorMessage = 'Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.';
            }
            
            $data = [
                'pageTitle' => 'Erro de Conexão',
                'error' => $errorMessage
            ];
            if (file_exists(APP_PATH . '/Views/errors/500.php')) {
                $this->view('errors/500', $data);
            } else {
                die('Erro: ' . htmlspecialchars($errorMessage) . ' <a href="' . base_url('logout') . '">Fazer logout</a>');
            }
        } catch (\Exception $e) {
            error_log('[DashboardController] Erro fatal: ' . $e->getMessage());
            error_log('[DashboardController] Stack trace: ' . $e->getTraceAsString());
            
            // Em caso de erro, redirecionar para login
            $this->redirectToLogin();
        }
    }
    
    private function redirectToLogin()
    {
        if (function_exists('base_url')) {
            header('Location: ' . base_url('login'));
        } else {
            $basePath = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $basePath . '/login.php');
        }
        exit;
    }
    
    private function redirectToLegacyDashboard($tipo)
    {
        $basePath = '';
        if (function_exists('base_url')) {
            // Usar base_url se disponível (sistema novo)
            $basePath = rtrim(base_url(), '/');
        } elseif (defined('BASE_PATH')) {
            // Usar BASE_PATH se definido (sistema antigo)
            $basePath = BASE_PATH;
        }
        
        switch ($tipo) {
            case 'instrutor':
                header('Location: ' . $basePath . '/instrutor/dashboard.php');
                exit;
                
            case 'aluno':
                header('Location: ' . $basePath . '/aluno/dashboard.php');
                exit;
                
            case 'admin':
            case 'secretaria':
                header('Location: ' . $basePath . '/admin/index.php');
                exit;
                
            default:
                // Se não conseguir determinar, redirecionar para login
                $this->redirectToLogin();
        }
    }
    
    private function dashboardAluno($userId)
    {
        $userModel = new User();
        $user = $userModel->findWithLinks($userId);
        
        if (!$user || empty($user['student_id'])) {
            // Aluno sem vínculo, mostrar mensagem
            $data = [
                'pageTitle' => 'Dashboard',
                'student' => null,
                // PWA Install Banner - CSS e JS para prompt de instalação
                'additionalCSS' => ['css/pwa-install-banner.css'],
                'additionalJS' => ['js/pwa-install-banner.js']
            ];
            $this->view('dashboard/aluno', $data);
            return;
        }
        
        $studentId = $user['student_id'];
        $studentModel = new Student();
        $enrollmentModel = new Enrollment();
        $lessonModel = new Lesson();
        $stepModel = new Step();
        $studentStepModel = new StudentStep();
        
        // Buscar dados do aluno
        $student = $studentModel->find($studentId);
        $enrollments = $enrollmentModel->findByStudent($studentId);
        $nextLesson = $lessonModel->findNextByStudent($studentId);
        
        // Buscar todas as aulas do aluno (para listagem completa)
        $allLessons = $lessonModel->findByStudent($studentId, 20); // Últimas 20 aulas
        
        // Separar aulas por categoria para exibição
        $today = date('Y-m-d');
        $upcomingLessons = []; // Agendadas futuras
        $inProgressLessons = []; // Em andamento
        $recentCompletedLessons = []; // Concluídas recentes (últimos 30 dias)
        
        foreach ($allLessons as $lesson) {
            $lessonDate = $lesson['scheduled_date'];
            $status = $lesson['status'] ?? '';
            
            if ($status === 'em_andamento') {
                $inProgressLessons[] = $lesson;
            } elseif ($status === 'agendada' && $lessonDate >= $today) {
                $upcomingLessons[] = $lesson;
            } elseif ($status === 'concluida') {
                // Só mostrar concluídas dos últimos 30 dias
                $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                if ($lessonDate >= $thirtyDaysAgo) {
                    $recentCompletedLessons[] = $lesson;
                }
            }
        }
        
        // Ordenar: próximas por data ASC, concluídas por data DESC
        usort($upcomingLessons, fn($a, $b) => strcmp($a['scheduled_date'] . $a['scheduled_time'], $b['scheduled_date'] . $b['scheduled_time']));
        usort($recentCompletedLessons, fn($a, $b) => strcmp($b['scheduled_date'] . $b['scheduled_time'], $a['scheduled_date'] . $a['scheduled_time']));
        
        // Verificar se existe solicitação pendente para a próxima aula
        $hasPendingRequest = false;
        if ($nextLesson) {
            $rescheduleRequestModel = new RescheduleRequest();
            $pendingRequest = $rescheduleRequestModel->findPendingByLessonAndStudent($nextLesson['id'], $studentId);
            $hasPendingRequest = !empty($pendingRequest);
        }
        
        // Buscar progresso da primeira matrícula ativa
        $activeEnrollment = null;
        $steps = [];
        $studentSteps = [];
        
        foreach ($enrollments as $enr) {
            if ($enr['status'] === 'ativa') {
                $activeEnrollment = $enr;
                break;
            }
        }
        
        if ($activeEnrollment) {
            $steps = $stepModel->findAllActive();
            $studentSteps = $studentStepModel->findByEnrollment($activeEnrollment['id']);
        }

        // Buscar dados do curso teórico (se matrícula tem turma vinculada)
        $theoryClass = null;
        $theoryEnrollments = [];
        $theoryProgress = null;
        
        if ($activeEnrollment && $activeEnrollment['theory_class_id']) {
            $theoryClassModel = new \App\Models\TheoryClass();
            $theoryClass = $theoryClassModel->findWithDetails($activeEnrollment['theory_class_id']);
            
            if ($theoryClass) {
                // Buscar matrícula do aluno na turma
                $theoryEnrollmentModel = new \App\Models\TheoryEnrollment();
                $theoryEnrollments = $theoryEnrollmentModel->findByStudent($studentId);
                
                // Buscar step CURSO_TEORICO
                $cursoTeoricoStep = $stepModel->findByCode('CURSO_TEORICO');
                if ($cursoTeoricoStep) {
                    $theoryStudentStep = $studentStepModel->findByEnrollmentAndStep($activeEnrollment['id'], $cursoTeoricoStep['id']);
                    
                    // Calcular progresso (% de disciplinas concluídas)
                    $sessionModel = new \App\Models\TheorySession();
                    $attendanceModel = new \App\Models\TheoryAttendance();
                    
                    // Buscar TODAS as sessões da turma (planejadas + concluídas)
                    $allSessions = $sessionModel->query(
                        "SELECT id, discipline_id, status FROM theory_sessions 
                         WHERE class_id = ?",
                        [$activeEnrollment['theory_class_id']]
                    )->fetchAll();
                    
                    $totalSessions = count($allSessions);
                    $attendedSessions = 0;
                    
                    if ($totalSessions > 0) {
                        // Filtrar apenas sessões concluídas para verificar presenças
                        $doneSessions = array_filter($allSessions, function($s) {
                            return $s['status'] === 'done';
                        });
                        $sessionIds = array_column($doneSessions, 'id');
                        
                        if (!empty($sessionIds)) {
                            $attendances = $attendanceModel->query(
                                "SELECT session_id FROM theory_attendance 
                                 WHERE student_id = ? 
                                   AND session_id IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")
                                   AND status IN ('present', 'justified')",
                                array_merge([$studentId], $sessionIds)
                            )->fetchAll();
                            
                            $attendedSessions = count($attendances);
                        }
                    }
                    
                    $progressPercent = $totalSessions > 0 ? round(($attendedSessions / $totalSessions) * 100) : 0;
                    
                    $theoryProgress = [
                        'step' => $theoryStudentStep,
                        'total_sessions' => $totalSessions,
                        'attended_sessions' => $attendedSessions,
                        'progress_percent' => $progressPercent,
                        'is_completed' => $theoryStudentStep && $theoryStudentStep['status'] === 'concluida'
                    ];
                }
            }
        }
        
        // Calcular situação financeira
        $totalDebt = 0;
        $totalPaid = 0;
        $hasPending = false;
        
        // Service para visualização de parcelas
        $installmentsService = new InstallmentsViewService();
        $allInstallments = [];
        
        foreach ($enrollments as $enr) {
            if ($enr['status'] !== 'cancelada') {
                $finalPrice = (float)$enr['final_price'];
                $entryAmount = (float)($enr['entry_amount'] ?? 0);
                
                $totalPaid += $entryAmount;
                $remainingDebt = max(0, $finalPrice - $entryAmount);
                $totalDebt += $remainingDebt;
                
                if ($remainingDebt > 0) {
                    $hasPending = true;
                }
                
                // Obter parcelas virtuais para esta matrícula
                $enrollmentInstallments = $installmentsService->getInstallmentsViewForEnrollment($enr);
                $allInstallments = array_merge($allInstallments, $enrollmentInstallments);
            }
        }
        
        // Calcular estatísticas agregadas das parcelas
        $installmentsStats = $installmentsService->getInstallmentsStats($allInstallments);
        $nextDueDate = $installmentsStats['next_due_date'];
        $overdueCount = $installmentsStats['overdue_count'];
        
        // Determinar status geral
        $statusGeral = 'Em andamento';
        if (empty($enrollments)) {
            $statusGeral = 'Sem matrícula';
        } elseif ($hasPending) {
            $statusGeral = 'Pendência financeira';
        } elseif (!empty($enrollments) && $enrollments[0]['status'] === 'concluida') {
            $statusGeral = 'Concluído';
        }
        
        $data = [
            'pageTitle' => 'Meu Progresso',
            'student' => $student,
            'enrollments' => $enrollments,
            'activeEnrollment' => $activeEnrollment,
            'nextLesson' => $nextLesson,
            'steps' => $steps,
            'studentSteps' => $studentSteps,
            'statusGeral' => $statusGeral,
            'totalDebt' => $totalDebt,
            'totalPaid' => $totalPaid,
            'hasPending' => $hasPending,
            'hasPendingRequest' => $hasPendingRequest,
            'theoryClass' => $theoryClass,
            'theoryProgress' => $theoryProgress,
            'nextDueDate' => $nextDueDate,
            'overdueCount' => $overdueCount,
            // Aulas do aluno separadas por categoria
            'upcomingLessons' => $upcomingLessons,
            'inProgressLessons' => $inProgressLessons,
            'recentCompletedLessons' => $recentCompletedLessons,
            // PWA Install Banner - CSS e JS para prompt de instalação
            'additionalCSS' => ['css/pwa-install-banner.css'],
            'additionalJS' => ['js/pwa-install-banner.js']
        ];
        
        $this->view('dashboard/aluno', $data);
    }
    
    private function dashboardInstrutor($userId)
    {
        try {
            // LOGGING: Início do método
            error_log('[DashboardController::dashboardInstrutor] Iniciando para user_id=' . $userId);
            error_log('[DashboardController::dashboardInstrutor] Session: user_id=' . ($_SESSION['user_id'] ?? 'N/A') . ', current_role=' . ($_SESSION['current_role'] ?? 'N/A') . ', user_type=' . ($_SESSION['user_type'] ?? 'N/A'));
            
            $userModel = new User();
            
            // Buscar usuário com links (Model já trata tabelas inexistentes internamente)
            $user = $userModel->findWithLinks($userId);
            error_log('[DashboardController::dashboardInstrutor] findWithLinks() executado');
            
            if (!$user) {
                error_log('[DashboardController::dashboardInstrutor] Usuário não encontrado');
                $data = [
                    'pageTitle' => 'Dashboard',
                    'instructor' => null
                ];
                $this->view('dashboard/instrutor', $data);
                return;
            }
            
            // Determinar instructor_id (fallback para user_id se não encontrar)
            $instructorId = $user['instructor_id'] ?? $userId;
            error_log('[DashboardController::dashboardInstrutor] Usando instructor_id: ' . $instructorId);
            
            $db = Database::getInstance()->getConnection();
            $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
            $today = date('Y-m-d');
            
            // Buscar próxima aula: prioridade para em_andamento, depois agendada mais próxima
            $nextLesson = null;
            try {
                // 1) Buscar aula em_andamento (prioridade máxima - está acontecendo agora)
                $stmtInProgress = $db->prepare(
                    "SELECT l.*,
                            COALESCE(s.full_name, s.name) as student_name,
                            v.plate as vehicle_plate
                     FROM lessons l
                     INNER JOIN students s ON l.student_id = s.id
                     LEFT JOIN vehicles v ON l.vehicle_id = v.id
                     WHERE l.instructor_id = ?
                       AND l.cfc_id = ?
                       AND l.status = 'em_andamento'
                     ORDER BY l.scheduled_date ASC, l.scheduled_time ASC
                     LIMIT 1"
                );
                $stmtInProgress->execute([$instructorId, $cfcId]);
                $nextLesson = $stmtInProgress->fetch();
                
                // 2) Se não há aula em andamento, buscar próxima agendada
                if (!$nextLesson) {
                    $now = date('H:i:s');
                    $stmtNext = $db->prepare(
                        "SELECT l.*,
                                COALESCE(s.full_name, s.name) as student_name,
                                v.plate as vehicle_plate
                         FROM lessons l
                         INNER JOIN students s ON l.student_id = s.id
                         LEFT JOIN vehicles v ON l.vehicle_id = v.id
                         WHERE l.instructor_id = ?
                           AND l.cfc_id = ?
                           AND l.status = 'agendada'
                           AND (l.scheduled_date > ? OR (l.scheduled_date = ? AND l.scheduled_time >= ?))
                         ORDER BY l.scheduled_date ASC, l.scheduled_time ASC
                         LIMIT 1"
                    );
                    $stmtNext->execute([$instructorId, $cfcId, $today, $today, $now]);
                    $nextLesson = $stmtNext->fetch();
                }
                
                error_log('[DashboardController::dashboardInstrutor] Próxima aula encontrada: ' . ($nextLesson ? 'sim (status=' . $nextLesson['status'] . ')' : 'não'));
            } catch (\PDOException $e) {
                error_log('[DashboardController::dashboardInstrutor] ERRO ao buscar próxima aula:');
                error_log('[DashboardController::dashboardInstrutor]   - Classe: ' . get_class($e));
                error_log('[DashboardController::dashboardInstrutor]   - SQLSTATE: ' . $e->getCode());
                error_log('[DashboardController::dashboardInstrutor]   - Mensagem: ' . $e->getMessage());
                error_log('[DashboardController::dashboardInstrutor]   - Arquivo: ' . $e->getFile() . ':' . $e->getLine());
                $nextLesson = null;
            }
            
            // Buscar aulas de hoje
            try {
                $stmt = $db->prepare(
                    "SELECT l.*,
                            COALESCE(s.full_name, s.name) as student_name,
                            v.plate as vehicle_plate
                     FROM lessons l
                     INNER JOIN students s ON l.student_id = s.id
                     LEFT JOIN vehicles v ON l.vehicle_id = v.id
                     WHERE l.instructor_id = ?
                       AND l.cfc_id = ?
                       AND l.scheduled_date = ?
                       AND l.status != 'cancelada'
                     ORDER BY l.scheduled_time ASC"
                );
                $stmt->execute([$instructorId, $cfcId, $today]);
                $todayLessons = $stmt->fetchAll();
                error_log('[DashboardController::dashboardInstrutor] Aulas de hoje encontradas: ' . count($todayLessons));
            } catch (\PDOException $e) {
                error_log('[DashboardController::dashboardInstrutor] ERRO ao buscar aulas de hoje:');
                error_log('[DashboardController::dashboardInstrutor]   - Classe: ' . get_class($e));
                error_log('[DashboardController::dashboardInstrutor]   - SQLSTATE: ' . $e->getCode());
                error_log('[DashboardController::dashboardInstrutor]   - Mensagem: ' . $e->getMessage());
                error_log('[DashboardController::dashboardInstrutor]   - Arquivo: ' . $e->getFile() . ':' . $e->getLine());
                $todayLessons = [];
            }
        
            // Contadores
            $totalToday = count($todayLessons);
            $completedToday = count(array_filter($todayLessons, function($l) {
                return $l['status'] === 'concluida';
            }));
            $pendingToday = $totalToday - $completedToday;
            
            error_log('[DashboardController::dashboardInstrutor] Preparando dados para view');
            
            $data = [
                'pageTitle' => 'Dashboard',
                'instructor' => $user,
                'nextLesson' => $nextLesson,
                'todayLessons' => $todayLessons,
                'totalToday' => $totalToday,
                'completedToday' => $completedToday,
                'pendingToday' => $pendingToday
            ];
            
            error_log('[DashboardController::dashboardInstrutor] Renderizando view dashboard/instrutor');
            $this->view('dashboard/instrutor', $data);
            
        } catch (\PDOException $e) {
            // LOGGING: Erro detalhado de PDO
            error_log('[DashboardController::dashboardInstrutor] ERRO PDOException:');
            error_log('[DashboardController::dashboardInstrutor]   - Classe: ' . get_class($e));
            error_log('[DashboardController::dashboardInstrutor]   - SQLSTATE: ' . $e->getCode());
            error_log('[DashboardController::dashboardInstrutor]   - Mensagem: ' . $e->getMessage());
            error_log('[DashboardController::dashboardInstrutor]   - Arquivo: ' . $e->getFile() . ':' . $e->getLine());
            error_log('[DashboardController::dashboardInstrutor]   - Stack trace: ' . $e->getTraceAsString());
            error_log('[DashboardController::dashboardInstrutor]   - Session: user_id=' . ($_SESSION['user_id'] ?? 'N/A') . ', current_role=' . ($_SESSION['current_role'] ?? 'N/A') . ', user_type=' . ($_SESSION['user_type'] ?? 'N/A'));
            
            // Re-lançar para ser capturado pelo try-catch do index()
            throw $e;
        } catch (\Exception $e) {
            // LOGGING: Erro geral
            error_log('[DashboardController::dashboardInstrutor] ERRO Exception:');
            error_log('[DashboardController::dashboardInstrutor]   - Classe: ' . get_class($e));
            error_log('[DashboardController::dashboardInstrutor]   - Mensagem: ' . $e->getMessage());
            error_log('[DashboardController::dashboardInstrutor]   - Arquivo: ' . $e->getFile() . ':' . $e->getLine());
            error_log('[DashboardController::dashboardInstrutor]   - Stack trace: ' . $e->getTraceAsString());
            error_log('[DashboardController::dashboardInstrutor]   - Session: user_id=' . ($_SESSION['user_id'] ?? 'N/A') . ', current_role=' . ($_SESSION['current_role'] ?? 'N/A') . ', user_type=' . ($_SESSION['user_type'] ?? 'N/A'));
            
            // Re-lançar para ser capturado pelo try-catch do index()
            throw $e;
        }
    }
    
    private function dashboardAdmin($userId)
    {
        $cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $db = Database::getInstance()->getConnection();
        $today = date('Y-m-d');
        $now = date('H:i:s');
        
        $lessonModel = new Lesson();
        $rescheduleRequestModel = new RescheduleRequest();
        $notificationModel = new Notification();
        $enrollmentModel = new Enrollment();
        
        // 1. Aulas de hoje (com contadores por status) - incluir canceladas para contadores
        $todayLessons = $lessonModel->findByPeriod($cfcId, $today, $today, ['show_canceled' => true]);
        
        // Contadores do dia
        $totalToday = count($todayLessons);
        $completedToday = count(array_filter($todayLessons, function($l) {
            return $l['status'] === Constants::AULA_CONCLUIDA;
        }));
        $inProgressToday = count(array_filter($todayLessons, function($l) {
            return $l['status'] === Constants::AULA_EM_ANDAMENTO;
        }));
        $scheduledToday = count(array_filter($todayLessons, function($l) {
            return $l['status'] === Constants::AULA_AGENDADA;
        }));
        $canceledToday = count(array_filter($todayLessons, function($l) {
            return $l['status'] === Constants::AULA_CANCELADA;
        }));
        
        // Ordenar aulas de hoje por horário
        usort($todayLessons, function($a, $b) {
            $timeA = strtotime($a['scheduled_time']);
            $timeB = strtotime($b['scheduled_time']);
            return $timeA <=> $timeB;
        });
        
        // 2. Próximas aulas (top 10 futuras, independente de hoje)
        // Buscar aulas futuras a partir de hoje (mas excluir as que já passaram hoje)
        $nextMonth = date('Y-m-d', strtotime('+30 days'));
        $allUpcoming = $lessonModel->findByPeriod($cfcId, $today, $nextMonth, ['show_canceled' => false]);
        
        // Filtrar apenas aulas que ainda não aconteceram (futuras)
        $upcomingLessons = array_filter($allUpcoming, function($lesson) use ($today, $now) {
            $lessonDate = $lesson['scheduled_date'];
            $lessonTime = $lesson['scheduled_time'];
            
            // Se for hoje, verificar se o horário ainda não passou
            if ($lessonDate === $today) {
                return $lessonTime >= $now;
            }
            // Se for futuro, incluir
            return $lessonDate > $today;
        });
        
        // Ordenar e limitar a 10
        usort($upcomingLessons, function($a, $b) {
            $dateA = strtotime($a['scheduled_date'] . ' ' . $a['scheduled_time']);
            $dateB = strtotime($b['scheduled_date'] . ' ' . $b['scheduled_time']);
            return $dateA <=> $dateB;
        });
        $upcomingLessons = array_slice($upcomingLessons, 0, 10);
        
        // 3. Solicitações de reagendamento pendentes (top 5)
        $pendingRequests = $rescheduleRequestModel->findPending($cfcId);
        $pendingRequests = array_slice($pendingRequests, 0, 5);
        $pendingRequestsCount = $rescheduleRequestModel->countPending($cfcId);
        
        // 4. Notificações não lidas (top 5) + contador total
        $unreadNotifications = $notificationModel->findByUser($userId, true, 5);
        $unreadNotificationsCount = $notificationModel->countUnread($userId);
        
        // 5. Resumo financeiro
        // Total recebido: soma de entry_amount de todas as matrículas não canceladas
        // Total a receber: soma de (final_price - entry_amount) onde final_price > entry_amount
        // Alunos com saldo devedor: contagem de alunos únicos com saldo > 0
        
        $stmt = $db->prepare(
            "SELECT 
                SUM(CASE WHEN e.status != 'cancelada' THEN COALESCE(e.entry_amount, 0) ELSE 0 END) as total_recebido,
                SUM(CASE WHEN e.status != 'cancelada' AND e.final_price > COALESCE(e.entry_amount, 0) 
                    THEN (e.final_price - COALESCE(e.entry_amount, 0)) ELSE 0 END) as total_a_receber
             FROM enrollments e
             INNER JOIN students s ON e.student_id = s.id
             WHERE s.cfc_id = ?"
        );
        $stmt->execute([$cfcId]);
        $financialSummary = $stmt->fetch();
        
        $totalRecebido = (float)($financialSummary['total_recebido'] ?? 0);
        $totalAReceber = (float)($financialSummary['total_a_receber'] ?? 0);
        
        // Contar alunos com saldo devedor > 0
        $stmt = $db->prepare(
            "SELECT COUNT(DISTINCT e.student_id) as qtd_devedores
             FROM enrollments e
             INNER JOIN students s ON e.student_id = s.id
             WHERE s.cfc_id = ?
               AND e.status != 'cancelada'
               AND e.final_price > COALESCE(e.entry_amount, 0)"
        );
        $stmt->execute([$cfcId]);
        $debtorsResult = $stmt->fetch();
        $qtdDevedores = (int)($debtorsResult['qtd_devedores'] ?? 0);
        
        // 6. Alertas: verificar se há aulas com reagendamento pendente para hoje/amanhã
        $hasUrgentReschedule = false;
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        foreach ($pendingRequests as $req) {
            $lessonDate = $req['scheduled_date'] ?? '';
            if ($lessonDate === $today || $lessonDate === $tomorrowDate) {
                $hasUrgentReschedule = true;
                break;
            }
        }
        
        $data = [
            'pageTitle' => 'Dashboard',
            'todayLessons' => $todayLessons,
            'totalToday' => $totalToday,
            'completedToday' => $completedToday,
            'inProgressToday' => $inProgressToday,
            'scheduledToday' => $scheduledToday,
            'canceledToday' => $canceledToday,
            'upcomingLessons' => $upcomingLessons,
            'pendingRequests' => $pendingRequests,
            'pendingRequestsCount' => $pendingRequestsCount,
            'unreadNotifications' => $unreadNotifications,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'totalRecebido' => $totalRecebido,
            'totalAReceber' => $totalAReceber,
            'qtdDevedores' => $qtdDevedores,
            'hasUrgentReschedule' => $hasUrgentReschedule
        ];
        
        $this->view('dashboard/admin', $data);
    }
}
