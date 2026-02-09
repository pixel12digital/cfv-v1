<?php

namespace App\Controllers;

use App\Models\Lesson;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Instructor;
use App\Models\InstructorAvailability;
use App\Models\Vehicle;
use App\Models\User;
use App\Models\RescheduleRequest;
use App\Services\EnrollmentPolicy;
use App\Services\StudentHistoryService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Config\Constants;
use App\Config\Database;

class AgendaController extends Controller
{
    private $cfcId;
    private $historyService;
    private $auditService;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->historyService = new StudentHistoryService();
        $this->auditService = new AuditService();
    }

    /**
     * Exibe o calendário da agenda
     */
    public function index()
    {
        try {
            $currentRole = $_SESSION['current_role'] ?? '';
            $userId = $_SESSION['user_id'] ?? null;
            $isAluno = ($currentRole === Constants::ROLE_ALUNO);
            $isInstrutor = ($currentRole === Constants::ROLE_INSTRUTOR);
            // Para ALUNO e INSTRUTOR, padrão é 'list', para outros perfis é 'week'
            $view = $_GET['view'] ?? (($isAluno || $isInstrutor) ? 'list' : 'week');
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $lessonModel = new Lesson();
            $instructorModel = new Instructor();
            $vehicleModel = new Vehicle();
            $userModel = new User();
            
            // Se for ALUNO, filtrar apenas aulas do próprio aluno
            $studentId = null;
            $loggedInstructorId = null;
            
            if ($isAluno && $userId) {
                $user = $userModel->findWithLinks($userId);
                if ($user && !empty($user['student_id'])) {
                    $studentId = $user['student_id'];
                }
            }
            
            // Se for INSTRUTOR, filtrar apenas aulas do próprio instrutor
            if ($isInstrutor && $userId) {
                $user = $userModel->findWithLinks($userId);
                if ($user && !empty($user['instructor_id'])) {
                    $loggedInstructorId = $user['instructor_id'];
                }
            }
        
        // Filtros (apenas para perfis administrativos, não para INSTRUTOR)
        $instructorId = ($isAluno || $isInstrutor) ? null : ($_GET['instructor_id'] ?? null);
        // Se for INSTRUTOR, forçar filtro pelo instrutor logado
        if ($isInstrutor && $loggedInstructorId) {
            $instructorId = $loggedInstructorId;
        }
        $vehicleId = ($isAluno || $isInstrutor) ? null : ($_GET['vehicle_id'] ?? null);
        $status = ($isAluno || $isInstrutor) ? null : ($_GET['status'] ?? null);
        $showCanceled = isset($_GET['show_canceled']) && $_GET['show_canceled'] == '1';
        
        // Filtro de abas para INSTRUTOR (Próximas, Histórico, Todas)
        $tab = $_GET['tab'] ?? 'proximas';
        if (!$isInstrutor) {
            $tab = 'todas'; // Para outros perfis, sempre "todas"
        }
        
        // Calcular período baseado na view
        // Para ALUNO em list view: mostrar TODAS as aulas (sem filtro de data por padrão)
        $dateFromUrl = $_GET['date'] ?? null; // Verificar se data foi explicitamente passada na URL
        
        if ($view === 'list') {
            // Para ALUNO: só filtrar por data se foi explicitamente selecionada
            if ($isAluno) {
                if ($dateFromUrl) {
                    // Aluno selecionou data específica: filtrar por esse dia
                    $startDate = $date;
                    $endDate = $date;
                } else {
                    // Aluno não selecionou data: mostrar TODAS as aulas
                    $startDate = null;
                    $endDate = null;
                }
            } elseif ($isInstrutor) {
                // CORREÇÃO: Para INSTRUTOR, ajustar período conforme a aba selecionada
                // Isso permite que "Histórico" mostre aulas passadas sem restrição de um único dia
                if ($dateFromUrl) {
                    // Instrutor selecionou data específica: filtrar por esse dia
                    $startDate = $date;
                    $endDate = $date;
                } elseif ($tab === 'historico') {
                    // Histórico: sem restrição de data inicial (ou últimos 12 meses), até hoje
                    $startDate = date('Y-m-d', strtotime('-12 months'));
                    $endDate = date('Y-m-d');
                } elseif ($tab === 'proximas') {
                    // Próximas: de hoje em diante (próximos 6 meses)
                    $startDate = date('Y-m-d');
                    $endDate = date('Y-m-d', strtotime('+6 months'));
                } else {
                    // Todas: período amplo (12 meses para trás e 6 para frente)
                    $startDate = date('Y-m-d', strtotime('-12 months'));
                    $endDate = date('Y-m-d', strtotime('+6 months'));
                }
            } else {
                // Para outros perfis (admin/secretaria): manter comportamento atual
                if ($date) {
                    $startDate = $date;
                    $endDate = $date;
                } else {
                    $startDate = null;
                    $endDate = null;
                }
            }
        } elseif ($view === 'day') {
            $startDate = $date;
            $endDate = $date;
        } else {
            // Semanal: domingo a sábado (começa no domingo)
            $dateObj = new \DateTime($date);
            $dayOfWeek = (int)$dateObj->format('w'); // 0 = domingo, 1 = segunda
            // Se não for domingo, voltar para o domingo da semana
            if ($dayOfWeek > 0) {
                $dateObj->modify("-{$dayOfWeek} days");
            }
            $startDate = $dateObj->format('Y-m-d');
            $dateObj->modify('+6 days');
            $endDate = $dateObj->format('Y-m-d');
        }
        
        // Se for aluno, buscar apenas suas aulas
        if ($isAluno && $studentId) {
            $allLessons = $lessonModel->findByStudent($studentId);
            if ($view === 'list') {
                // Lista: se há data específica, filtrar por data
                if ($startDate && $endDate) {
                    $lessons = array_filter($allLessons, function($lesson) use ($startDate, $endDate) {
                        return $lesson['scheduled_date'] >= $startDate && $lesson['scheduled_date'] <= $endDate;
                    });
                    $lessons = array_values($lessons);
                } else {
                    // Sem data específica: todas as aulas
                    $lessons = $allLessons;
                }
            } else {
                // Filtrar por período
                $lessons = array_filter($allLessons, function($lesson) use ($startDate, $endDate) {
                    return $lesson['scheduled_date'] >= $startDate && $lesson['scheduled_date'] <= $endDate;
                });
                $lessons = array_values($lessons);
            }
        } elseif ($isInstrutor && $loggedInstructorId && $view === 'list') {
            // Para INSTRUTOR em view=list, usar método com dedupe de sessões teóricas
            // Adicionar filtro de data se houver
            $instructorFilters = ['tab' => $tab];
            if ($startDate && $endDate) {
                $instructorFilters['start_date'] = $startDate;
                $instructorFilters['end_date'] = $endDate;
            }
            $lessons = $lessonModel->findByInstructorWithTheoryDedupe(
                $loggedInstructorId, 
                $this->cfcId, 
                $instructorFilters
            );
        } else {
            // Para admin/secretaria: usar método com dedupe de teóricas
            $filters = array_filter([
                'instructor_id' => $instructorId,
                'vehicle_id' => $vehicleId,
                'status' => $status,
                'type' => $_GET['type'] ?? null,
                'show_canceled' => $showCanceled
            ]);
            
            // Ajustar período se necessário
            if ($view === 'list') {
                // Se já calculamos período baseado na data, usar ele
                // Senão, usar período amplo
                if (!$startDate || !$endDate) {
                    $startDate = date('Y-m-d', strtotime('-6 months'));
                    $endDate = date('Y-m-d', strtotime('+6 months'));
                }
            } elseif (!$startDate || !$endDate) {
                // Se não há período definido, usar data atual como referência
                $startDate = $startDate ?: date('Y-m-d', strtotime('-1 month'));
                $endDate = $endDate ?: date('Y-m-d', strtotime('+1 month'));
            }
            
            $lessons = $lessonModel->findByPeriodWithTheoryDedupe($this->cfcId, $startDate, $endDate, $filters);
            
            // Log SQL para auditoria quando view=list e date está definido
            if ($view === 'list' && $date && isset($_GET['date'])) {
                error_log("=== AGENDA SQL AUDIT ===");
                error_log("View: list, Date: {$date}");
                error_log("StartDate: {$startDate}, EndDate: {$endDate}");
                error_log("Filters: " . json_encode($filters));
                error_log("Lessons count: " . count($lessons));
                // A SQL será logada dentro do método findByPeriodWithTheoryDedupe
            }
        }
        
        $instructors = ($isAluno || $isInstrutor) ? [] : $instructorModel->findAvailableForAgenda($this->cfcId);
        $vehicles = ($isAluno || $isInstrutor) ? [] : $vehicleModel->findActive($this->cfcId);
        
        $data = [
            'pageTitle' => ($isAluno || $isInstrutor) ? 'Minha Agenda' : 'Agenda',
            'viewType' => $view,
            'date' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'lessons' => $lessons,
            'instructors' => $instructors,
            'vehicles' => $vehicles,
            'filters' => [
                'instructor_id' => $instructorId,
                'vehicle_id' => $vehicleId,
                'status' => $status,
                'type' => $_GET['type'] ?? null,
                'show_canceled' => $showCanceled
            ],
            'showCanceled' => $showCanceled,
            'isAluno' => $isAluno,
            'isInstrutor' => $isInstrutor,
            'tab' => $tab
        ];
        
        $this->view('agenda/index', $data);
        } catch (\PDOException $e) {
            error_log("[AgendaController::index] PDOException capturada:");
            error_log("  Classe: " . get_class($e));
            error_log("  SQLSTATE: " . $e->getCode());
            error_log("  Mensagem: " . $e->getMessage());
            error_log("  Arquivo: " . $e->getFile() . ":" . $e->getLine());
            error_log("  Stack trace: " . $e->getTraceAsString());
            error_log("  Sessão: " . json_encode([
                'user_id' => $_SESSION['user_id'] ?? null,
                'current_role' => $_SESSION['current_role'] ?? null,
                'user_type' => $_SESSION['user_type'] ?? null,
            ]));
            error_log("  REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
            
            // Exibir mensagem amigável
            $_SESSION['error'] = 'Erro ao carregar a agenda. Por favor, tente novamente mais tarde.';
            redirect(base_url('dashboard'));
        } catch (\Exception $e) {
            error_log("[AgendaController::index] Exception capturada:");
            error_log("  Classe: " . get_class($e));
            error_log("  Mensagem: " . $e->getMessage());
            error_log("  Arquivo: " . $e->getFile() . ":" . $e->getLine());
            error_log("  Stack trace: " . $e->getTraceAsString());
            
            $_SESSION['error'] = 'Erro ao carregar a agenda. Por favor, tente novamente mais tarde.';
            redirect(base_url('dashboard'));
        }
    }

    /**
     * Formulário para criar nova aula
     */
    public function novo()
    {
        $studentId = $_GET['student_id'] ?? null;
        $enrollmentId = $_GET['enrollment_id'] ?? null;
        
        $studentModel = new Student();
        $enrollmentModel = new Enrollment();
        $instructorModel = new Instructor();
        $vehicleModel = new Vehicle();
        
        // Carregar TODOS os alunos do CFC para o select
        $allStudents = $studentModel->findByCfc($this->cfcId);
        
        $student = null;
        $enrollment = null;
        $enrollments = [];
        
        if ($studentId) {
            $student = $studentModel->find($studentId);
            if ($student && $student['cfc_id'] == $this->cfcId) {
                $allEnrollments = $enrollmentModel->findByStudent($studentId);
                $enrollments = array_values(array_filter($allEnrollments, fn($e) => ($e['status'] ?? '') !== 'cancelada'));
                $lessonModel = new Lesson();
                foreach ($enrollments as &$e) {
                    $e['aulas_contratadas'] = isset($e['aulas_contratadas']) && $e['aulas_contratadas'] !== null ? (int)$e['aulas_contratadas'] : null;
                    $e['aulas_agendadas'] = $lessonModel->countScheduledByEnrollment($e['id']);
                    $e['aulas_faltantes'] = $e['aulas_contratadas'] !== null ? max(0, $e['aulas_contratadas'] - $e['aulas_agendadas']) : null;
                }
                unset($e);
                if ($enrollmentId) {
                    $enrollment = $enrollmentModel->find($enrollmentId);
                    if (!$enrollment || ($enrollment['status'] ?? '') === 'cancelada') {
                        $enrollment = $enrollments[0] ?? null;
                    }
                } elseif (!empty($enrollments)) {
                    $enrollment = $enrollments[0];
                }
            }
        }
        
        $data = [
            'pageTitle' => 'Nova Aula',
            'students' => $allStudents, // Lista completa de alunos para o select
            'student' => $student,
            'enrollment' => $enrollment,
            'enrollments' => $enrollments,
            'instructors' => $instructorModel->findAvailableForAgenda($this->cfcId), // Apenas com credencial válida
            'vehicles' => $vehicleModel->findActive($this->cfcId)
        ];
        
        $this->view('agenda/form', $data);
    }

    /**
     * Cria uma nova aula
     */
    public function criar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('agenda'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        $studentId = $_POST['student_id'] ?? null;
        $enrollmentId = $_POST['enrollment_id'] ?? null;
        $notes = $_POST['notes'] ?? null;
        $durationMinutes = Constants::DURACAO_AULA_PADRAO;

        // Suportar blocos múltiplos ou bloco único (legado)
        $blocksRaw = $_POST['blocks'] ?? null;
        if (is_array($blocksRaw) && !empty($blocksRaw)) {
            $blocks = [];
            foreach ($blocksRaw as $idx => $b) {
                if (!empty($b['instructor_id']) && !empty($b['vehicle_id']) && !empty($b['scheduled_date']) && !empty($b['scheduled_time'])) {
                    $blocks[] = [
                        'instructor_id' => $b['instructor_id'],
                        'vehicle_id' => $b['vehicle_id'],
                        'scheduled_date' => $b['scheduled_date'],
                        'scheduled_time' => $b['scheduled_time'],
                        'lesson_count' => max(1, min(6, (int)($b['lesson_count'] ?? 1)))
                    ];
                }
            }
        } else {
            $blocks = [[
                'instructor_id' => $_POST['instructor_id'] ?? null,
                'vehicle_id' => $_POST['vehicle_id'] ?? null,
                'scheduled_date' => $_POST['scheduled_date'] ?? null,
                'scheduled_time' => $_POST['scheduled_time'] ?? null,
                'lesson_count' => max(1, min(6, (int)($_POST['lesson_count'] ?? 1)))
            ]];
        }

        if (!$studentId || !$enrollmentId || empty($blocks)) {
            $_SESSION['error'] = 'Preencha todos os campos obrigatórios.';
            redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
        }

        $totalLessonCount = array_sum(array_column($blocks, 'lesson_count'));
        
        // Validar matrícula e bloqueio financeiro
        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->find($enrollmentId);
        
        if (!$enrollment || $enrollment['student_id'] != $studentId) {
            $_SESSION['error'] = 'Matrícula inválida.';
            redirect(base_url('agenda/novo'));
        }
        
        if (!EnrollmentPolicy::canSchedule($enrollment)) {
            $_SESSION['error'] = 'Não é possível agendar aulas para esta matrícula. Aluno com situação financeira bloqueada.';
            redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
        }

        $lessonModel = new Lesson();

        // Validar limite de aulas contratadas (se definido na matrícula)
        $aulasContratadas = isset($enrollment['aulas_contratadas']) && $enrollment['aulas_contratadas'] !== null
            ? (int)$enrollment['aulas_contratadas']
            : null;
        if ($aulasContratadas !== null) {
            $aulasAgendadas = $lessonModel->countScheduledByEnrollment($enrollmentId);
            $aulasFaltantes = max(0, $aulasContratadas - $aulasAgendadas);
            if ($totalLessonCount > $aulasFaltantes) {
                $_SESSION['error'] = "A matrícula permite agendar no máximo {$aulasFaltantes} aula(s) restante(s). Contratadas: {$aulasContratadas}, já agendadas: {$aulasAgendadas}.";
                redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
            }
        }

        $instructorModel = new Instructor();
        $studentModel = new Student();
        $student = $studentModel->find($studentId);
        $availabilityModel = new InstructorAvailability();
        $allCreatedLessons = [];
        $lastInstructorName = '';

        foreach ($blocks as $blockIdx => $block) {
            $instructorId = $block['instructor_id'];
            $vehicleId = $block['vehicle_id'];
            $scheduledDate = $block['scheduled_date'];
            $scheduledTime = $block['scheduled_time'];
            $lessonCount = $block['lesson_count'];

            $instructor = $instructorModel->find($instructorId);
            if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
                $_SESSION['error'] = 'Instrutor não encontrado no bloco ' . ($blockIdx + 1) . '.';
                redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
            }
            if ($instructorModel->isCredentialExpired($instructor)) {
                $_SESSION['error'] = 'Não é possível agendar: a credencial do instrutor está vencida (bloco ' . ($blockIdx + 1) . ').';
                redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
            }

            $dayOfWeek = (int)date('w', strtotime($scheduledDate));
            $availability = $availabilityModel->findByInstructorAndDay($instructorId, $dayOfWeek);
            $totalDuration = $lessonCount * $durationMinutes;
            if ($availability && $availability['is_available']) {
                $scheduledTimeObj = new \DateTime($scheduledTime);
                $endTimeObj = clone $scheduledTimeObj;
                $endTimeObj->modify("+{$totalDuration} minutes");
                $startTime = new \DateTime($availability['start_time']);
                $endTime = new \DateTime($availability['end_time']);
                if ($scheduledTimeObj < $startTime || $endTimeObj > $endTime) {
                    $_SESSION['error'] = "Bloco " . ($blockIdx + 1) . ": instrutor não disponível neste horário. Disponível: {$availability['start_time']} às {$availability['end_time']}.";
                    redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
                }
            }

            $currentTime = $scheduledTime;
            for ($i = 0; $i < $lessonCount; $i++) {
                if ($lessonModel->hasInstructorConflict($instructorId, $scheduledDate, $currentTime, $durationMinutes, null, $this->cfcId)) {
                    $_SESSION['error'] = "Conflito: instrutor já possui aula no bloco " . ($blockIdx + 1) . ", horário {$currentTime}.";
                    redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
                }
                if ($lessonModel->hasVehicleConflict($vehicleId, $scheduledDate, $currentTime, $durationMinutes, null, $this->cfcId)) {
                    $_SESSION['error'] = "Conflito: veículo já possui aula no bloco " . ($blockIdx + 1) . ", horário {$currentTime}.";
                    redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
                }
                if ($i < $lessonCount - 1) {
                    $timeObj = new \DateTime("{$scheduledDate} {$currentTime}");
                    $timeObj->modify("+{$durationMinutes} minutes");
                    $currentTime = $timeObj->format('H:i:s');
                }
            }

            $currentTime = $scheduledTime;
            for ($i = 0; $i < $lessonCount; $i++) {
                $data = [
                    'cfc_id' => $this->cfcId,
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId,
                    'instructor_id' => $instructorId,
                    'vehicle_id' => $vehicleId,
                    'type' => 'pratica',
                    'status' => Constants::AULA_AGENDADA,
                    'scheduled_date' => $scheduledDate,
                    'scheduled_time' => $currentTime,
                    'duration_minutes' => $durationMinutes,
                    'notes' => $notes ?: null,
                    'created_by' => $_SESSION['user_id'] ?? null
                ];
                $lessonId = $lessonModel->create($data);
                $allCreatedLessons[] = $lessonId;
                $this->auditService->logCreate('agenda', $lessonId, $data);
                if ($i < $lessonCount - 1) {
                    $timeObj = new \DateTime("{$scheduledDate} {$currentTime}");
                    $timeObj->modify("+{$durationMinutes} minutes");
                    $currentTime = $timeObj->format('H:i:s');
                }
            }
            $lastInstructorName = $instructor['name'];

            $dateTime = date('d/m/Y H:i', strtotime("{$scheduledDate} {$scheduledTime}"));
            if ($lessonCount === 1) {
                $this->historyService->logAgendaEvent($studentId, "Aula prática agendada para {$dateTime} — Instrutor: {$lastInstructorName}");
            } else {
                $totalMinutes = $lessonCount * $durationMinutes;
                $startDateTime = new \DateTime("{$scheduledDate} {$scheduledTime}");
                $endDateTime = clone $startDateTime;
                $endDateTime->modify("+{$totalMinutes} minutes");
                $this->historyService->logAgendaEvent($studentId,
                    "{$lessonCount} aulas práticas consecutivas agendadas — {$dateTime} até " . $endDateTime->format('d/m/Y H:i') . " — Instrutor: {$lastInstructorName}");
            }
        }

        $_SESSION['success'] = $totalLessonCount === 1
            ? 'Aula agendada com sucesso!'
            : "{$totalLessonCount} aulas agendadas com sucesso!";
        
        // PRG Pattern: Redirect imediato após POST
        redirect(base_url('agenda'));
    }

    /**
     * Exibe detalhes de uma aula
     */
    public function show($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        $lessonModel = new Lesson();
        $lesson = $lessonModel->findWithDetails($id);
        
        if (!$lesson || $lesson['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }
        
        // Se for ALUNO, verificar se a aula pertence a ele
        $studentId = null;
        if ($currentRole === Constants::ROLE_ALUNO) {
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $userModel = new \App\Models\User();
                $user = $userModel->findWithLinks($userId);
                if (empty($user['student_id']) || $lesson['student_id'] != $user['student_id']) {
                    $_SESSION['error'] = 'Aula não encontrada.';
                    redirect(base_url('agenda'));
                }
                $studentId = $user['student_id'];
            }
        }
        
        // Verificar se existe solicitação pendente (apenas para ALUNO)
        $hasPendingRequest = false;
        if ($currentRole === Constants::ROLE_ALUNO && $studentId) {
            $rescheduleRequestModel = new RescheduleRequest();
            $pendingRequest = $rescheduleRequestModel->findPendingByLessonAndStudent($id, $studentId);
            $hasPendingRequest = !empty($pendingRequest);
        }
        
        $from = $_GET['from'] ?? null;
        $isAluno = ($currentRole === Constants::ROLE_ALUNO);
        $isInstrutor = ($currentRole === Constants::ROLE_INSTRUTOR);
        
        // Buscar resumo do aluno para exibir ao instrutor
        $studentSummary = null;
        if ($isInstrutor && $lesson['instructor_id'] && $lesson['student_id'] && $lesson['enrollment_id']) {
            $studentSummary = $lessonModel->getStudentSummaryForInstructor(
                $lesson['instructor_id'],
                $lesson['student_id'],
                $lesson['enrollment_id']
            );
        }
        
        // Buscar aulas consecutivas do mesmo aluno no mesmo dia
        $consecutiveBlock = $this->findConsecutiveBlock($lesson, $lessonModel);
        
        $data = [
            'pageTitle' => 'Detalhes da Aula',
            'lesson' => $lesson,
            'currentRole' => $currentRole,
            'from' => $from,
            'isAluno' => $isAluno,
            'hasPendingRequest' => $hasPendingRequest,
            'studentSummary' => $studentSummary,
            'consecutiveBlock' => $consecutiveBlock
        ];
        
        $this->view('agenda/show', $data);
    }

    /**
     * Formulário para editar/remarcar aula
     */
    public function editar($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        // ALUNO não pode remarcar aulas
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para remarcar aulas. Entre em contato com a secretaria.';
            redirect(base_url('agenda/' . $id));
        }
        
        $lessonModel = new Lesson();
        $lesson = $lessonModel->findWithDetails($id);
        
        if (!$lesson || $lesson['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }
        
        // Não permitir editar aulas concluídas ou canceladas
        if (in_array($lesson['status'], ['concluida', 'cancelada'])) {
            $_SESSION['error'] = 'Não é possível editar aulas concluídas ou canceladas.';
            redirect(base_url('agenda/' . $id));
        }
        
        $studentModel = new Student();
        $enrollmentModel = new Enrollment();
        $instructorModel = new Instructor();
        $vehicleModel = new Vehicle();
        
        $student = $studentModel->find($lesson['student_id']);
        $allEnrollments = $enrollmentModel->findByStudent($lesson['student_id']);
        $enrollments = array_values(array_filter($allEnrollments, fn($e) => ($e['status'] ?? '') !== 'cancelada'));
        
        $data = [
            'pageTitle' => 'Remarcar Aula',
            'lesson' => $lesson,
            'student' => $student,
            'enrollments' => $enrollments,
            'instructors' => $instructorModel->findAvailableForAgenda($this->cfcId), // Apenas com credencial válida
            'vehicles' => $vehicleModel->findActive($this->cfcId)
        ];
        
        $this->view('agenda/form', $data);
    }

    /**
     * Atualiza/remarca uma aula
     */
    public function atualizar($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        // ALUNO não pode remarcar aulas
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para remarcar aulas. Entre em contato com a secretaria.';
            redirect(base_url('agenda/' . $id));
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('agenda'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        $lessonModel = new Lesson();
        $lesson = $lessonModel->find($id);
        
        if (!$lesson || $lesson['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }
        
        // Não permitir editar aulas concluídas ou canceladas
        if (in_array($lesson['status'], ['concluida', 'cancelada'])) {
            $_SESSION['error'] = 'Não é possível editar aulas concluídas ou canceladas.';
            redirect(base_url('agenda/' . $id));
        }
        
        $instructorId = $_POST['instructor_id'] ?? $lesson['instructor_id'];
        $vehicleId = $_POST['vehicle_id'] ?? $lesson['vehicle_id'];
        $scheduledDate = $_POST['scheduled_date'] ?? $lesson['scheduled_date'];
        $scheduledTime = $_POST['scheduled_time'] ?? $lesson['scheduled_time'];
        $durationMinutes = (int)($_POST['duration_minutes'] ?? $lesson['duration_minutes']);
        $notes = $_POST['notes'] ?? $lesson['notes'];
        
        // Validar veículo obrigatório
        if (!$vehicleId) {
            $_SESSION['error'] = 'Veículo é obrigatório para aulas práticas.';
            redirect(base_url('agenda/' . $id . '/editar'));
        }
        
        // Validar disponibilidade do instrutor
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($instructorId);
        
        if ($instructor) {
            // Verificar se credencial está vencida
            if ($instructorModel->isCredentialExpired($instructor)) {
                $_SESSION['error'] = 'Não é possível remarcar: a credencial do instrutor está vencida.';
                redirect(base_url('agenda/' . $id . '/editar'));
            }
            
            // Verificar disponibilidade de horário
            $availabilityModel = new InstructorAvailability();
            $dayOfWeek = (int)date('w', strtotime($scheduledDate));
            $scheduledTimeObj = new \DateTime($scheduledTime);
            $endTimeObj = clone $scheduledTimeObj;
            $endTimeObj->modify("+{$durationMinutes} minutes");
            
            $availability = $availabilityModel->findByInstructorAndDay($instructorId, $dayOfWeek);
            // Se há disponibilidade configurada, validar o horário
            if ($availability && $availability['is_available']) {
                $startTime = new \DateTime($availability['start_time']);
                $endTime = new \DateTime($availability['end_time']);
                
                if ($scheduledTimeObj < $startTime || $endTimeObj > $endTime) {
                    $_SESSION['error'] = "O instrutor não está disponível neste horário. Horário disponível: {$availability['start_time']} às {$availability['end_time']}.";
                    redirect(base_url('agenda/' . $id . '/editar'));
                }
            }
            // Se não há disponibilidade configurada, permite agendamento (não bloqueia)
        }
        
        // Validar conflitos (excluindo a própria aula)
        if ($lessonModel->hasInstructorConflict($instructorId, $scheduledDate, $scheduledTime, $durationMinutes, $id, $this->cfcId)) {
            $_SESSION['error'] = 'Conflito de horário: o instrutor já possui uma aula agendada neste horário.';
            redirect(base_url('agenda/' . $id . '/editar'));
        }
        
        if ($lessonModel->hasVehicleConflict($vehicleId, $scheduledDate, $scheduledTime, $durationMinutes, $id, $this->cfcId)) {
            $_SESSION['error'] = 'Conflito de horário: o veículo já possui uma aula agendada neste horário.';
            redirect(base_url('agenda/' . $id . '/editar'));
        }
        
        // Atualizar
        $dataBefore = $lesson;
        $updateData = [
            'instructor_id' => $instructorId,
            'vehicle_id' => $vehicleId,
            'type' => 'pratica',
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => $scheduledTime,
            'duration_minutes' => $durationMinutes,
            'notes' => $notes ?: null
        ];
        
        $lessonModel->update($id, $updateData);
        
        // Registrar no histórico
        $studentModel = new Student();
        $student = $studentModel->find($lesson['student_id']);
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($instructorId);
        $dateTime = date('d/m/Y H:i', strtotime("{$scheduledDate} {$scheduledTime}"));
        
        $this->historyService->logAgendaEvent(
            $lesson['student_id'],
            "Aula prática remarcada para {$dateTime} com instrutor {$instructor['name']}"
        );
        
        // Auditoria
        $this->auditService->logUpdate('agenda', $id, $dataBefore, array_merge($lesson, $updateData));
        
        $_SESSION['success'] = 'Aula remarcada com sucesso!';
        redirect(base_url('agenda/' . $id));
    }

    /**
     * Cancela uma aula
     */
    public function cancelar($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        // ALUNO não pode cancelar aulas
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para cancelar aulas. Entre em contato com a secretaria.';
            redirect(base_url('agenda/' . $id));
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('agenda'));
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        $lessonModel = new Lesson();
        $lesson = $lessonModel->findWithDetails($id);
        
        if (!$lesson || $lesson['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }
        
        // Não permitir cancelar aulas já concluídas ou canceladas
        if (in_array($lesson['status'], ['concluida', 'cancelada'])) {
            $_SESSION['error'] = 'Esta aula já foi concluída ou cancelada.';
            redirect(base_url('agenda/' . $id));
        }
        
        $reason = trim($_POST['reason'] ?? '');
        if (empty($reason)) {
            $reason = 'Sem motivo informado';
        }
        
        // Buscar detalhes para histórico
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($lesson['instructor_id']);
        $vehicleModel = new Vehicle();
        $vehicle = $vehicleModel->find($lesson['vehicle_id']);
        
        $dateTime = date('d/m/Y H:i', strtotime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}"));
        $instructorName = $instructor['name'] ?? 'N/A';
        $vehiclePlate = $vehicle['plate'] ?? 'N/A';
        
        // Atualizar status e campos de cancelamento
        $dataBefore = $lesson;
        $updateData = [
            'status' => Constants::AULA_CANCELADA,
            'canceled_at' => date('Y-m-d H:i:s'),
            'canceled_by' => $_SESSION['user_id'] ?? null,
            'cancel_reason' => $reason
        ];
        
        // Adicionar motivo nas notas se houver
        if ($reason && $reason !== 'Sem motivo informado') {
            $updateData['notes'] = ($lesson['notes'] ? $lesson['notes'] . "\n\n" : '') . "Cancelada em " . date('d/m/Y H:i') . ". Motivo: {$reason}";
        }
        
        $lessonModel->update($id, $updateData);
        
        // Registrar no histórico do aluno
        $historyMessage = "Aula prática cancelada — {$dateTime} — Instrutor: {$instructorName} — Veículo: {$vehiclePlate}";
        if ($reason && $reason !== 'Sem motivo informado') {
            $historyMessage .= " — Motivo: {$reason}";
        }
        
        $this->historyService->logAgendaEvent(
            $lesson['student_id'],
            $historyMessage
        );
        
        // Auditoria
        $this->auditService->logUpdate('agenda', $id, $dataBefore, array_merge($lesson, $updateData));
        
        $_SESSION['success'] = 'Aula cancelada com sucesso!';
        redirect(base_url('agenda'));
    }

    /**
     * Conclui uma aula
     */
    public function concluir($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        // ALUNO não pode concluir aulas (apenas INSTRUTOR, ADMIN, SECRETARIA)
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para concluir aulas.';
            redirect(base_url('agenda/' . $id));
        }
        
        $lessonModel = new Lesson();
        $lesson = $lessonModel->findWithDetails($id);
        
        if (!$lesson || $lesson['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }
        
        // Só pode concluir aulas agendadas ou em andamento
        if (!in_array($lesson['status'], [Constants::AULA_AGENDADA, Constants::AULA_EM_ANDAMENTO])) {
            $_SESSION['error'] = 'Esta aula não pode ser concluída.';
            redirect(base_url('agenda/' . $id));
        }
        
        // Validação RBAC: Se for INSTRUTOR, só pode concluir suas próprias aulas
        if ($currentRole === Constants::ROLE_INSTRUTOR && $userId) {
            $userModel = new User();
            $user = $userModel->findWithLinks($userId);
            if (empty($user['instructor_id']) || $user['instructor_id'] != $lesson['instructor_id']) {
                $_SESSION['error'] = 'Você só pode concluir suas próprias aulas.';
                redirect(base_url('agenda/' . $id));
            }
        }
        
        // Se for GET, mostrar modal/formulário
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = [
                'pageTitle' => 'Concluir Aula',
                'lesson' => $lesson
            ];
            $this->view('agenda/concluir', $data);
            return;
        }
        
        // POST: processar conclusão da aula
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        // Validar km final (obrigatório)
        $kmEnd = isset($_POST['km_end']) ? trim($_POST['km_end']) : '';
        if (empty($kmEnd)) {
            $_SESSION['error'] = 'Quilometragem final é obrigatória.';
            redirect(base_url('agenda/' . $id . '/concluir'));
        }
        
        // Validar que é um número inteiro positivo
        $kmEnd = (int)$kmEnd;
        if ($kmEnd < 0) {
            $_SESSION['error'] = 'Quilometragem final deve ser um número positivo.';
            redirect(base_url('agenda/' . $id . '/concluir'));
        }
        
        // Validar que km final >= km inicial (se km inicial existir)
        if (!empty($lesson['km_start']) && $kmEnd < $lesson['km_start']) {
            $_SESSION['error'] = 'Quilometragem final deve ser maior ou igual à quilometragem inicial (' . $lesson['km_start'] . ' km).';
            redirect(base_url('agenda/' . $id . '/concluir'));
        }
        
        // Atualizar status e km final
        $dataBefore = $lesson;
        $now = date('Y-m-d H:i:s');
        
        $updateData = [
            'status' => Constants::AULA_CONCLUIDA,
            'completed_at' => $now,
            'km_end' => $kmEnd
        ];
        
        // Se estava apenas agendada, marcar como iniciada também
        if ($lesson['status'] === Constants::AULA_AGENDADA) {
            $updateData['started_at'] = $now;
            // Se não tinha km inicial, usar o km final como inicial também (caso especial)
            if (empty($lesson['km_start'])) {
                $updateData['km_start'] = $kmEnd;
            }
        }
        
        // Observação do instrutor (opcional, mas se já existir, adicionar)
        if (!empty($_POST['instructor_notes'])) {
            $notes = trim($_POST['instructor_notes']);
            // Se já existir observação, adicionar nova linha
            if (!empty($lesson['instructor_notes'])) {
                $updateData['instructor_notes'] = $lesson['instructor_notes'] . "\n\n" . date('d/m/Y H:i') . " - " . $notes;
            } else {
                $updateData['instructor_notes'] = $notes;
            }
        }
        
        $lessonModel->update($id, $updateData);
        
        // Registrar no histórico
        $dateTime = date('d/m/Y H:i', strtotime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}"));
        $kmDiff = $kmEnd - ($lesson['km_start'] ?? $kmEnd);
        
        $this->historyService->logAgendaEvent(
            $lesson['student_id'],
            "Aula prática concluída (agendada para {$dateTime}) - KM final: {$kmEnd} (percorrido: {$kmDiff} km)"
        );
        
        // Auditoria
        $this->auditService->logUpdate('agenda', $id, $dataBefore, array_merge($lesson, $updateData));
        
        $_SESSION['success'] = 'Aula concluída com sucesso!';
        redirect(base_url('agenda/' . $id));
    }

    /**
     * Inicia uma aula
     */
    public function iniciar($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        // ALUNO não pode iniciar aulas (apenas INSTRUTOR, ADMIN, SECRETARIA)
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para iniciar aulas.';
            redirect(base_url('agenda/' . $id));
        }
        
        $lessonModel = new Lesson();
        $lesson = $lessonModel->findWithDetails($id);
        
        if (!$lesson || $lesson['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }
        
        // Só pode iniciar aulas agendadas
        if ($lesson['status'] !== Constants::AULA_AGENDADA) {
            $_SESSION['error'] = 'Esta aula não pode ser iniciada.';
            redirect(base_url('agenda/' . $id));
        }
        
        // Validação RBAC: Se for INSTRUTOR, só pode iniciar suas próprias aulas
        if ($currentRole === Constants::ROLE_INSTRUTOR && $userId) {
            $userModel = new User();
            $user = $userModel->findWithLinks($userId);
            if (empty($user['instructor_id']) || $user['instructor_id'] != $lesson['instructor_id']) {
                $_SESSION['error'] = 'Você só pode iniciar suas próprias aulas.';
                redirect(base_url('agenda/' . $id));
            }
        }
        
        // Se for GET, mostrar modal/formulário
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Buscar resumo do aluno para exibir contador
            $studentSummary = $lessonModel->getStudentSummaryForInstructor(
                $lesson['instructor_id'],
                $lesson['student_id'],
                $lesson['enrollment_id']
            );
            
            $data = [
                'pageTitle' => 'Iniciar Aula',
                'lesson' => $lesson,
                'studentSummary' => $studentSummary
            ];
            $this->view('agenda/iniciar', $data);
            return;
        }
        
        // POST: processar início da aula
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        // Validar tipo(s) de aula prática (obrigatório, permite múltiplos)
        $practiceTypes = isset($_POST['practice_type']) && is_array($_POST['practice_type'])
            ? array_map('trim', $_POST['practice_type'])
            : [];
        $practiceTypes = array_filter($practiceTypes);
        $validPracticeTypes = ['rua', 'garagem', 'baliza'];
        $invalid = array_diff($practiceTypes, $validPracticeTypes);
        if (empty($practiceTypes) || !empty($invalid)) {
            $_SESSION['error'] = 'Selecione pelo menos um tipo de aula (Rua, Garagem ou Baliza).';
            redirect(base_url('agenda/' . $id . '/iniciar'));
        }
        $practiceType = implode(',', array_unique($practiceTypes));
        
        // Validar km inicial (obrigatório)
        $kmStart = isset($_POST['km_start']) ? trim($_POST['km_start']) : '';
        if (empty($kmStart)) {
            $_SESSION['error'] = 'Quilometragem inicial é obrigatória.';
            redirect(base_url('agenda/' . $id . '/iniciar'));
        }
        
        // Validar que é um número inteiro positivo
        $kmStart = (int)$kmStart;
        if ($kmStart < 0) {
            $_SESSION['error'] = 'Quilometragem inicial deve ser um número positivo.';
            redirect(base_url('agenda/' . $id . '/iniciar'));
        }
        
        // Validar bloqueio financeiro
        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->find($lesson['enrollment_id']);
        
        if (!EnrollmentPolicy::canStartLesson($enrollment)) {
            $_SESSION['error'] = 'Não é possível iniciar a aula. Aluno com situação financeira bloqueada.';
            redirect(base_url('agenda/' . $id));
        }
        
        // Atualizar status e km inicial
        $dataBefore = $lesson;
        $now = date('Y-m-d H:i:s');
        
        $updateData = [
            'status' => Constants::AULA_EM_ANDAMENTO,
            'started_at' => $now,
            'km_start' => $kmStart,
            'practice_type' => $practiceType
        ];
        
        // Observação inicial opcional (pode ser usado como observação geral)
        if (!empty($_POST['instructor_notes'])) {
            $updateData['instructor_notes'] = trim($_POST['instructor_notes']);
        }
        
        $lessonModel->update($id, $updateData);
        
        // Registrar no histórico
        $dateTime = date('d/m/Y H:i', strtotime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}"));
        
        // Mapear tipo(s) para exibição
        $practiceTypeLabels = ['rua' => 'Rua', 'garagem' => 'Garagem', 'baliza' => 'Baliza'];
        $labels = array_map(function ($t) use ($practiceTypeLabels) {
            return $practiceTypeLabels[$t] ?? $t;
        }, explode(',', $practiceType));
        $practiceTypeLabel = implode(', ', $labels);
        
        $this->historyService->logAgendaEvent(
            $lesson['student_id'],
            "Aula prática ({$practiceTypeLabel}) iniciada (agendada para {$dateTime}) - KM inicial: {$kmStart}"
        );
        
        // Auditoria
        $this->auditService->logUpdate('agenda', $id, $dataBefore, array_merge($lesson, $updateData));
        
        $_SESSION['success'] = 'Aula iniciada com sucesso!';
        redirect(base_url('agenda/' . $id));
    }

    /**
     * Inicia um bloco de aulas práticas consecutivas
     */
    public function iniciarBloco()
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para iniciar aulas.';
            redirect(base_url('agenda'));
        }
        
        $idsStr = $_GET['ids'] ?? $_POST['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $idsStr)));
        if (empty($ids)) {
            $_SESSION['error'] = 'IDs das aulas não informados.';
            redirect(base_url('agenda'));
        }
        
        $lessonModel = new Lesson();
        $lessons = [];
        foreach ($ids as $id) {
            $lesson = $lessonModel->findWithDetails($id);
            if (!$lesson || $lesson['cfc_id'] != $this->cfcId) continue;
            if ($lesson['status'] !== Constants::AULA_AGENDADA) continue;
            if ($currentRole === Constants::ROLE_INSTRUTOR && $userId) {
                $userModel = new User();
                $user = $userModel->findWithLinks($userId);
                if (empty($user['instructor_id']) || $user['instructor_id'] != $lesson['instructor_id']) continue;
            }
            $lessons[] = $lesson;
        }
        
        if (empty($lessons)) {
            $_SESSION['error'] = 'Nenhuma aula agendada encontrada para iniciar no bloco.';
            redirect(base_url('agenda'));
        }
        
        $firstLesson = $lessons[0];
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $studentSummary = $lessonModel->getStudentSummaryForInstructor(
                $firstLesson['instructor_id'],
                $firstLesson['student_id'],
                $firstLesson['enrollment_id']
            );
            $data = [
                'pageTitle' => 'Iniciar Bloco',
                'lessons' => $lessons,
                'lesson' => $firstLesson,
                'studentSummary' => $studentSummary,
                'ids' => implode(',', $ids)
            ];
            $this->view('agenda/iniciar-bloco', $data);
            return;
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        $practiceTypes = isset($_POST['practice_type']) && is_array($_POST['practice_type'])
            ? array_map('trim', $_POST['practice_type']) : [];
        $practiceTypes = array_filter($practiceTypes);
        $validPracticeTypes = ['rua', 'garagem', 'baliza'];
        $invalid = array_diff($practiceTypes, $validPracticeTypes);
        if (empty($practiceTypes) || !empty($invalid)) {
            $_SESSION['error'] = 'Selecione pelo menos um tipo de aula (Rua, Garagem ou Baliza).';
            redirect(base_url('agenda/iniciar-bloco?ids=' . urlencode(implode(',', $ids))));
        }
        $practiceType = implode(',', array_unique($practiceTypes));
        
        $kmStart = isset($_POST['km_start']) ? (int)trim($_POST['km_start']) : 0;
        if ($kmStart < 0) {
            $_SESSION['error'] = 'Quilometragem inicial deve ser um número positivo.';
            redirect(base_url('agenda/iniciar-bloco?ids=' . urlencode(implode(',', $ids))));
        }
        
        $enrollmentModel = new Enrollment();
        $enrollment = $enrollmentModel->find($firstLesson['enrollment_id']);
        if (!EnrollmentPolicy::canStartLesson($enrollment)) {
            $_SESSION['error'] = 'Não é possível iniciar. Aluno com situação financeira bloqueada.';
            redirect(base_url('agenda'));
        }
        
        $now = date('Y-m-d H:i:s');
        $idsToUpdate = array_column($lessons, 'id');
        
        foreach ($idsToUpdate as $lid) {
            $lessonModel->update($lid, [
                'status' => Constants::AULA_EM_ANDAMENTO,
                'started_at' => $now,
                'km_start' => $kmStart,
                'practice_type' => $practiceType
            ]);
        }
        
        $dateTime = date('d/m/Y H:i', strtotime("{$firstLesson['scheduled_date']} {$firstLesson['scheduled_time']}"));
        $this->historyService->logAgendaEvent(
            $firstLesson['student_id'],
            "Bloco de " . count($lessons) . " aula(s) iniciado (agendado para {$dateTime}) - KM inicial: {$kmStart}"
        );
        
        $_SESSION['success'] = 'Bloco iniciado com sucesso (' . count($lessons) . ' aula(s))!';
        redirect(base_url('agenda/' . $firstLesson['id']));
    }

    /**
     * Finaliza um bloco de aulas práticas consecutivas
     */
    public function finalizarBloco()
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;
        
        if ($currentRole === Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para finalizar aulas.';
            redirect(base_url('agenda'));
        }
        
        $idsStr = $_GET['ids'] ?? $_POST['ids'] ?? '';
        $ids = array_filter(array_map('intval', explode(',', $idsStr)));
        if (empty($ids)) {
            $_SESSION['error'] = 'IDs das aulas não informados.';
            redirect(base_url('agenda'));
        }
        
        $lessonModel = new Lesson();
        $lessons = [];
        foreach ($ids as $id) {
            $lesson = $lessonModel->findWithDetails($id);
            if (!$lesson || $lesson['cfc_id'] != $this->cfcId) continue;
            if (!in_array($lesson['status'], [Constants::AULA_AGENDADA, Constants::AULA_EM_ANDAMENTO])) continue;
            if ($currentRole === Constants::ROLE_INSTRUTOR && $userId) {
                $userModel = new User();
                $user = $userModel->findWithLinks($userId);
                if (empty($user['instructor_id']) || $user['instructor_id'] != $lesson['instructor_id']) continue;
            }
            $lessons[] = $lesson;
        }
        
        if (empty($lessons)) {
            $_SESSION['error'] = 'Nenhuma aula em andamento encontrada para finalizar no bloco.';
            redirect(base_url('agenda'));
        }
        
        $firstLesson = $lessons[0];
        $kmInicialRef = $firstLesson['km_start'] ?? null;
        foreach ($lessons as $l) {
            if (($l['status'] ?? '') === Constants::AULA_EM_ANDAMENTO && !empty($l['km_start'])) {
                $kmInicialRef = $l['km_start'];
                break;
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = [
                'pageTitle' => 'Finalizar Bloco',
                'lessons' => $lessons,
                'lesson' => $firstLesson,
                'ids' => implode(',', array_column($lessons, 'id'))
            ];
            $this->view('agenda/finalizar-bloco', $data);
            return;
        }
        
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
        $kmEnd = isset($_POST['km_end']) ? (int)trim($_POST['km_end']) : 0;
        if ($kmEnd < 0) {
            $_SESSION['error'] = 'Quilometragem final deve ser um número positivo.';
            redirect(base_url('agenda/finalizar-bloco?ids=' . urlencode(implode(',', $ids))));
        }
        if ($kmInicialRef !== null && $kmEnd < $kmInicialRef) {
            $_SESSION['error'] = 'KM final não pode ser menor que KM inicial (' . $kmInicialRef . ' km).';
            redirect(base_url('agenda/finalizar-bloco?ids=' . urlencode(implode(',', $ids))));
        }
        
        $now = date('Y-m-d H:i:s');
        foreach ($lessons as $l) {
            $lessonModel->update($l['id'], [
                'status' => Constants::AULA_CONCLUIDA,
                'completed_at' => $now,
                'km_end' => $kmEnd
            ]);
        }
        
        $dateTime = date('d/m/Y H:i', strtotime("{$firstLesson['scheduled_date']} {$firstLesson['scheduled_time']}"));
        $this->historyService->logAgendaEvent(
            $firstLesson['student_id'],
            "Bloco de " . count($lessons) . " aula(s) finalizado - KM final: {$kmEnd}"
        );
        
        $_SESSION['success'] = 'Bloco finalizado com sucesso (' . count($lessons) . ' aula(s))!';
        redirect(base_url('agenda/' . $firstLesson['id']));
    }

    /**
     * API: Busca aulas para calendário (AJAX)
     */
    public function apiCalendario()
    {
        $startDate = $_GET['start'] ?? date('Y-m-d');
        $endDate = $_GET['end'] ?? date('Y-m-d');
        
        $filters = array_filter([
            'instructor_id' => $_GET['instructor_id'] ?? null,
            'vehicle_id' => $_GET['vehicle_id'] ?? null,
            'type' => $_GET['type'] ?? null,
            'status' => $_GET['status'] ?? null
        ]);
        
        $lessonModel = new Lesson();
        $lessons = $lessonModel->findByPeriod($this->cfcId, $startDate, $endDate, $filters);
        
        // Formatar para FullCalendar ou similar
        $events = [];
        foreach ($lessons as $lesson) {
            $start = new \DateTime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}");
            $end = clone $start;
            $end->modify("+{$lesson['duration_minutes']} minutes");
            
            $events[] = [
                'id' => $lesson['id'],
                'title' => $lesson['student_name'] . ' - ' . $lesson['instructor_name'],
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
                'type' => $lesson['type'],
                'status' => $lesson['status'],
                'instructor' => $lesson['instructor_name'],
                'vehicle' => $lesson['vehicle_plate'] ?? null
            ];
        }
        
        $this->json($events);
    }
    
    /**
     * Encontra o bloco de aulas consecutivas que inclui a aula fornecida
     * @param array $lesson Aula de referência
     * @param \App\Models\Lesson $lessonModel Model de aulas
     * @return array|null Dados do bloco ou null se não houver consecutivas
     */
    private function findConsecutiveBlock(array $lesson, $lessonModel): ?array
    {
        // Buscar todas as aulas do mesmo aluno no mesmo dia
        $allLessons = $lessonModel->findByStudentAndDate(
            $lesson['student_id'],
            $lesson['scheduled_date']
        );
        
        if (count($allLessons) < 2) {
            return null;
        }
        
        // Encontrar o bloco que contém a aula atual
        $blocks = [];
        $currentBlock = null;
        
        foreach ($allLessons as $l) {
            if ($currentBlock === null) {
                $currentBlock = ['lessons' => [$l]];
            } else {
                $lastLesson = end($currentBlock['lessons']);
                $lastEndTime = new \DateTime($lastLesson['scheduled_date'] . ' ' . $lastLesson['scheduled_time']);
                $lastEndTime->modify("+{$lastLesson['duration_minutes']} minutes");
                
                $currentStartTime = new \DateTime($l['scheduled_date'] . ' ' . $l['scheduled_time']);
                
                // Verificar se é consecutiva (horário de término = horário de início)
                if ($lastEndTime->format('H:i') == $currentStartTime->format('H:i')) {
                    $currentBlock['lessons'][] = $l;
                } else {
                    $blocks[] = $currentBlock;
                    $currentBlock = ['lessons' => [$l]];
                }
            }
        }
        $blocks[] = $currentBlock;
        
        // Encontrar qual bloco contém a aula atual
        foreach ($blocks as $block) {
            $lessonIds = array_column($block['lessons'], 'id');
            if (in_array($lesson['id'], $lessonIds)) {
                if (count($block['lessons']) > 1) {
                    // Calcular dados do bloco
                    $firstLesson = $block['lessons'][0];
                    $lastLesson = end($block['lessons']);
                    
                    $startTime = new \DateTime($firstLesson['scheduled_date'] . ' ' . $firstLesson['scheduled_time']);
                    $totalDuration = array_sum(array_column($block['lessons'], 'duration_minutes'));
                    $endTime = clone $startTime;
                    $endTime->modify("+{$totalDuration} minutes");
                    
                    return [
                        'lessons' => $block['lessons'],
                        'count' => count($block['lessons']),
                        'first_lesson_id' => $firstLesson['id'],
                        'start_time' => $startTime->format('H:i'),
                        'end_time' => $endTime->format('H:i'),
                        'total_duration' => $totalDuration,
                        'is_first' => ($lesson['id'] == $firstLesson['id']),
                        'is_last' => ($lesson['id'] == $lastLesson['id'])
                    ];
                }
                break;
            }
        }
        
        return null;
    }
}
