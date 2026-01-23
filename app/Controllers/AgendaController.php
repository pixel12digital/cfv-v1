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
        if ($view === 'list') {
            // Lista: se há data específica, usar EXATAMENTE esse dia (00:00-23:59)
            // Se não há data, buscar período amplo
            if ($date) {
                // Data específica selecionada: usar EXATAMENTE esse dia
                $startDate = $date;
                $endDate = $date;
            } else {
                // Sem data específica: período amplo
                $startDate = null;
                $endDate = null;
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
                $enrollments = $enrollmentModel->findByStudent($studentId);
                if ($enrollmentId) {
                    $enrollment = $enrollmentModel->find($enrollmentId);
                } elseif (!empty($enrollments)) {
                    $enrollment = $enrollments[0]; // Primeira matrícula ativa
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
        $instructorId = $_POST['instructor_id'] ?? null;
        $vehicleId = $_POST['vehicle_id'] ?? null;
        $scheduledDate = $_POST['scheduled_date'] ?? null;
        $scheduledTime = $_POST['scheduled_time'] ?? null;
        $lessonCount = (int)($_POST['lesson_count'] ?? 1); // Quantidade de aulas (1 ou 2)
        $durationMinutes = Constants::DURACAO_AULA_PADRAO; // Sempre 50 minutos por aula
        $notes = $_POST['notes'] ?? null;
        
        // Validações básicas
        if (!$studentId || !$enrollmentId || !$instructorId || !$vehicleId || !$scheduledDate || !$scheduledTime) {
            $_SESSION['error'] = 'Preencha todos os campos obrigatórios.';
            redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
        }
        
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
        
        // Validar disponibilidade do instrutor
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($instructorId);
        
        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
        }
        
        // Verificar se credencial está vencida
        if ($instructorModel->isCredentialExpired($instructor)) {
            $_SESSION['error'] = 'Não é possível agendar: a credencial do instrutor está vencida.';
            redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
        }
        
        // Verificar disponibilidade de horário (OPCIONAL - se não configurada, permite qualquer horário)
        $availabilityModel = new InstructorAvailability();
        $dayOfWeek = (int)date('w', strtotime($scheduledDate)); // 0=Domingo, 1=Segunda, etc
        $availability = $availabilityModel->findByInstructorAndDay($instructorId, $dayOfWeek);
        
        // Calcular duração total (considerando quantidade de aulas)
        $totalDuration = $lessonCount * $durationMinutes;
        
        // Se há disponibilidade configurada, validar o horário considerando a duração total
        if ($availability && $availability['is_available']) {
            $scheduledTimeObj = new \DateTime($scheduledTime);
            $endTimeObj = clone $scheduledTimeObj;
            $endTimeObj->modify("+{$totalDuration} minutes"); // Duração total das aulas
            
            $startTime = new \DateTime($availability['start_time']);
            $endTime = new \DateTime($availability['end_time']);
            
            if ($scheduledTimeObj < $startTime || $endTimeObj > $endTime) {
                $_SESSION['error'] = "O instrutor não está disponível neste horário. Horário disponível: {$availability['start_time']} às {$availability['end_time']}.";
                redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
            }
        }
        // Se não há disponibilidade configurada, permite agendamento (não bloqueia)
        
        // Validar conflitos (ANTES de criar as aulas)
        // Validar cada aula individualmente para garantir que não há conflito em nenhum intervalo
        $lessonModel = new Lesson();
        $currentTime = $scheduledTime;
        
        for ($i = 0; $i < $lessonCount; $i++) {
            // Validar conflito para esta aula específica
            if ($lessonModel->hasInstructorConflict($instructorId, $scheduledDate, $currentTime, $durationMinutes, null, $this->cfcId)) {
                $lessonNumber = $i + 1;
                $_SESSION['error'] = "Conflito de horário: o instrutor já possui uma aula agendada no horário da aula {$lessonNumber} ({$currentTime}).";
                redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
            }
            
            if ($lessonModel->hasVehicleConflict($vehicleId, $scheduledDate, $currentTime, $durationMinutes, null, $this->cfcId)) {
                $lessonNumber = $i + 1;
                $_SESSION['error'] = "Conflito de horário: o veículo já possui uma aula agendada no horário da aula {$lessonNumber} ({$currentTime}).";
                redirect(base_url('agenda/novo?' . http_build_query(['student_id' => $studentId, 'enrollment_id' => $enrollmentId])));
            }
            
            // Calcular horário da próxima aula (se houver)
            if ($i < $lessonCount - 1) {
                $timeObj = new \DateTime("{$scheduledDate} {$currentTime}");
                $timeObj->modify("+{$durationMinutes} minutes");
                $currentTime = $timeObj->format('H:i:s');
            }
        }
        
        // Criar aulas (1 ou 2 consecutivas)
        $studentModel = new Student();
        $student = $studentModel->find($studentId);
        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($instructorId);
        
        $createdLessons = [];
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
            $createdLessons[] = $lessonId;
            
            // Calcular horário da próxima aula (se houver)
            if ($i < $lessonCount - 1) {
                $timeObj = new \DateTime("{$scheduledDate} {$currentTime}");
                $timeObj->modify("+{$durationMinutes} minutes");
                $currentTime = $timeObj->format('H:i:s');
            }
        }
        
        // Registrar no histórico
        $dateTime = date('d/m/Y H:i', strtotime("{$scheduledDate} {$scheduledTime}"));
        if ($lessonCount === 2) {
            // Calcular horário final corretamente: 2 aulas = 100 minutos
            $startDateTime = new \DateTime("{$scheduledDate} {$scheduledTime}");
            $endDateTime = clone $startDateTime;
            $endDateTime->modify("+100 minutes"); // 2 aulas × 50 minutos
            $endTime = $endDateTime->format('H:i');
            $endDate = $endDateTime->format('d/m/Y');
            
            $this->historyService->logAgendaEvent(
                $studentId,
                "2 aulas práticas consecutivas agendadas — {$dateTime} até {$endDate} {$endTime} — Instrutor: {$instructor['name']}"
            );
        } else {
            $this->historyService->logAgendaEvent(
                $studentId,
                "Aula prática agendada para {$dateTime} — Instrutor: {$instructor['name']}"
            );
        }
        
        // Auditoria
        foreach ($createdLessons as $lessonId) {
            $this->auditService->logCreate('agenda', $lessonId, $data);
        }
        
        // Mensagem de sucesso
        if ($lessonCount === 2) {
            $_SESSION['success'] = '2 aulas consecutivas agendadas com sucesso!';
        } else {
            $_SESSION['success'] = 'Aula agendada com sucesso!';
        }
        
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
        
        $data = [
            'pageTitle' => 'Detalhes da Aula',
            'lesson' => $lesson,
            'currentRole' => $currentRole,
            'from' => $from,
            'isAluno' => $isAluno,
            'hasPendingRequest' => $hasPendingRequest
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
        $enrollments = $enrollmentModel->findByStudent($lesson['student_id']);
        
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
            $data = [
                'pageTitle' => 'Iniciar Aula',
                'lesson' => $lesson
            ];
            $this->view('agenda/iniciar', $data);
            return;
        }
        
        // POST: processar início da aula
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda'));
        }
        
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
            'km_start' => $kmStart
        ];
        
        // Observação inicial opcional (pode ser usado como observação geral)
        if (!empty($_POST['instructor_notes'])) {
            $updateData['instructor_notes'] = trim($_POST['instructor_notes']);
        }
        
        $lessonModel->update($id, $updateData);
        
        // Registrar no histórico
        $dateTime = date('d/m/Y H:i', strtotime("{$lesson['scheduled_date']} {$lesson['scheduled_time']}"));
        
        $this->historyService->logAgendaEvent(
            $lesson['student_id'],
            "Aula prática iniciada (agendada para {$dateTime}) - KM inicial: {$kmStart}"
        );
        
        // Auditoria
        $this->auditService->logUpdate('agenda', $id, $dataBefore, array_merge($lesson, $updateData));
        
        $_SESSION['success'] = 'Aula iniciada com sucesso!';
        redirect(base_url('agenda/' . $id));
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
}
