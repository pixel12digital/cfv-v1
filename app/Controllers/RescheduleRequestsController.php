<?php

namespace App\Controllers;

use App\Models\RescheduleRequest;
use App\Models\Lesson;
use App\Models\Notification;
use App\Models\User;
use App\Models\Student;
use App\Config\Constants;
use App\Config\Database;

class RescheduleRequestsController extends Controller
{
    private $cfcId;
    private $requestModel;
    private $notificationModel;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->requestModel = new RescheduleRequest();
        $this->notificationModel = new Notification();
    }

    /**
     * ALUNO: Criar solicitação de reagendamento
     */
    public function solicitar($lessonId)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        // Apenas ALUNO pode criar solicitações
        if ($currentRole !== Constants::ROLE_ALUNO) {
            $_SESSION['error'] = 'Você não tem permissão para realizar esta ação.';
            redirect(base_url('agenda/' . $lessonId));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('agenda/' . $lessonId));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('agenda/' . $lessonId));
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            redirect(base_url('login'));
        }

        $userModel = new User();
        $user = $userModel->findWithLinks($userId);
        
        if (empty($user['student_id'])) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('agenda/' . $lessonId));
        }

        $studentId = $user['student_id'];
        $lessonModel = new Lesson();
        $lesson = $lessonModel->find($lessonId);

        // Validar que a aula pertence ao aluno
        if (!$lesson || $lesson['student_id'] != $studentId) {
            $_SESSION['error'] = 'Aula não encontrada.';
            redirect(base_url('agenda'));
        }

        // Não permitir reagendamento de aula teórica
        $isTheory = ($lesson['type'] ?? '') === 'teoria' || !empty($lesson['theory_session_id']);
        if ($isTheory) {
            $_SESSION['error'] = 'Solicitação de reagendamento não está disponível para aulas teóricas.';
            redirect(base_url('agenda/' . $lessonId));
        }

        // Validar que a aula é futura e está agendada
        $lessonDateTime = new \DateTime($lesson['scheduled_date'] . ' ' . $lesson['scheduled_time']);
        $now = new \DateTime();
        
        if ($lessonDateTime < $now) {
            $_SESSION['error'] = 'Não é possível solicitar reagendamento de aulas passadas.';
            redirect(base_url('agenda/' . $lessonId));
        }

        if ($lesson['status'] !== Constants::AULA_AGENDADA) {
            $_SESSION['error'] = 'Apenas aulas agendadas podem ter reagendamento solicitado.';
            redirect(base_url('agenda/' . $lessonId));
        }

        // Verificar se já existe solicitação pendente
        $existingRequest = $this->requestModel->findPendingByLessonAndStudent($lessonId, $studentId);
        if ($existingRequest) {
            $_SESSION['error'] = 'Já existe uma solicitação pendente para esta aula.';
            redirect(base_url('agenda/' . $lessonId));
        }

        $reason = $_POST['reason'] ?? '';
        $message = trim($_POST['message'] ?? '');

        // Validar motivo
        $validReasons = ['imprevisto', 'trabalho', 'saude', 'outro'];
        if (!in_array($reason, $validReasons)) {
            $_SESSION['error'] = 'Motivo inválido.';
            redirect(base_url('agenda/' . $lessonId));
        }

        // Criar solicitação
        $requestData = [
            'lesson_id' => $lessonId,
            'student_id' => $studentId,
            'user_id' => $userId,
            'status' => 'pending',
            'reason' => $reason,
            'message' => $message ?: null
        ];

        $requestId = $this->requestModel->create($requestData);

        // Notificar SECRETARIA/ADMIN
        $this->notifyAdmins($lesson, $studentId, $lessonDateTime, $requestId);

        $_SESSION['success'] = 'Solicitação de reagendamento enviada com sucesso! A secretaria entrará em contato.';
        redirect(base_url('agenda/' . $lessonId));
    }

    /**
     * ADMIN/SECRETARIA: Listar solicitações pendentes
     */
    public function index()
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        // Apenas ADMIN e SECRETARIA podem gerenciar solicitações
        if ($currentRole !== Constants::ROLE_ADMIN && $currentRole !== Constants::ROLE_SECRETARIA) {
            $_SESSION['error'] = 'Você não tem permissão para acessar este módulo.';
            redirect(base_url('dashboard'));
        }

        $status = $_GET['status'] ?? 'pending';
        $requests = [];

        if ($status === 'pending') {
            $requests = $this->requestModel->findPending($this->cfcId);
        } else {
            // TODO: Implementar filtros por status se necessário
            $requests = [];
        }

        $data = [
            'pageTitle' => 'Solicitações de Reagendamento',
            'requests' => $requests,
            'status' => $status
        ];

        $this->view('reschedule_requests/index', $data);
    }

    /**
     * ADMIN/SECRETARIA: Aprovar solicitação
     */
    public function aprovar($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        if ($currentRole !== Constants::ROLE_ADMIN && $currentRole !== Constants::ROLE_SECRETARIA) {
            $_SESSION['error'] = 'Você não tem permissão para realizar esta ação.';
            redirect(base_url('dashboard'));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('solicitacoes-reagendamento'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('solicitacoes-reagendamento'));
        }

        $request = $this->requestModel->findWithDetails($id);
        if (!$request || $request['status'] !== 'pending') {
            $_SESSION['error'] = 'Solicitação não encontrada ou já foi processada.';
            redirect(base_url('solicitacoes-reagendamento'));
        }

        $userId = $_SESSION['user_id'] ?? null;
        $resolutionNote = trim($_POST['resolution_note'] ?? '');

        // Atualizar solicitação
        $this->requestModel->update($id, [
            'status' => 'approved',
            'resolved_by_user_id' => $userId,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution_note' => $resolutionNote ?: null
        ]);

        // Notificar aluno
        $this->notifyStudent($request, 'approved', $resolutionNote);

        // Marcar notificações relacionadas como lidas
        $this->markRequestNotificationsAsRead($id, $userId);

        $_SESSION['success'] = 'Solicitação aprovada. Aluno será notificado.';
        redirect(base_url('solicitacoes-reagendamento'));
    }

    /**
     * ADMIN/SECRETARIA: Recusar solicitação
     */
    public function recusar($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        if ($currentRole !== Constants::ROLE_ADMIN && $currentRole !== Constants::ROLE_SECRETARIA) {
            $_SESSION['error'] = 'Você não tem permissão para realizar esta ação.';
            redirect(base_url('dashboard'));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('solicitacoes-reagendamento'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('solicitacoes-reagendamento'));
        }

        $request = $this->requestModel->findWithDetails($id);
        if (!$request || $request['status'] !== 'pending') {
            $_SESSION['error'] = 'Solicitação não encontrada ou já foi processada.';
            redirect(base_url('solicitacoes-reagendamento'));
        }

        $userId = $_SESSION['user_id'] ?? null;
        $resolutionNote = trim($_POST['resolution_note'] ?? '');

        // Atualizar solicitação
        $this->requestModel->update($id, [
            'status' => 'rejected',
            'resolved_by_user_id' => $userId,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution_note' => $resolutionNote ?: null
        ]);

        // Notificar aluno
        $this->notifyStudent($request, 'rejected', $resolutionNote);

        // Marcar notificações relacionadas como lidas
        $this->markRequestNotificationsAsRead($id, $userId);

        $_SESSION['success'] = 'Solicitação recusada. Aluno será notificado.';
        redirect(base_url('solicitacoes-reagendamento'));
    }

    /**
     * Notifica SECRETARIA/ADMIN sobre nova solicitação
     */
    private function notifyAdmins($lesson, $studentId, $lessonDateTime, $requestId)
    {
        $db = Database::getInstance()->getConnection();
        
        // Buscar usuários ADMIN e SECRETARIA
        $stmt = $db->prepare("
            SELECT DISTINCT u.id 
            FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            WHERE u.cfc_id = ? 
            AND u.status = 'ativo'
            AND ur.role IN ('ADMIN', 'SECRETARIA')
        ");
        $stmt->execute([$this->cfcId]);
        $admins = $stmt->fetchAll();

        $studentModel = new Student();
        $student = $studentModel->find($studentId);
        $studentName = $student['full_name'] ?? $student['name'] ?? 'Aluno';

        $lessonDateStr = $lessonDateTime->format('d/m/Y');
        $lessonTimeStr = $lessonDateTime->format('H:i');

        $title = 'Solicitação de Reagendamento';
        $body = "{$studentName} solicitou reagendamento da aula em {$lessonDateStr} às {$lessonTimeStr}";
        // Link aponta para a solicitação específica
        $link = base_path("solicitacoes-reagendamento/{$requestId}");

        foreach ($admins as $admin) {
            $this->notificationModel->createNotification(
                $admin['id'],
                'reschedule_request',
                $title,
                $body,
                $link
            );
        }
    }

    /**
     * Notifica aluno sobre resolução da solicitação
     */
    private function notifyStudent($request, $status, $resolutionNote = null)
    {
        // Buscar user_id do aluno
        $studentModel = new Student();
        $student = $studentModel->find($request['student_id']);
        
        if (empty($student['user_id'])) {
            return; // Aluno não tem usuário vinculado
        }

        $lessonDateStr = date('d/m/Y', strtotime($request['scheduled_date']));
        $lessonTimeStr = date('H:i', strtotime($request['scheduled_time']));

        if ($status === 'approved') {
            $title = 'Solicitação Aprovada';
            $body = "Sua solicitação de reagendamento da aula em {$lessonDateStr} às {$lessonTimeStr} foi aprovada.";
            if ($resolutionNote) {
                $body .= " Observação: {$resolutionNote}";
            }
        } else {
            $title = 'Solicitação Recusada';
            $body = "Sua solicitação de reagendamento da aula em {$lessonDateStr} às {$lessonTimeStr} foi recusada.";
            if ($resolutionNote) {
                $body .= " Motivo: {$resolutionNote}";
            }
        }

        $link = base_path('agenda/' . $request['lesson_id']);

        $this->notificationModel->createNotification(
            $student['user_id'],
            'reschedule_response',
            $title,
            $body,
            $link
        );
    }

    /**
     * Marca notificações relacionadas a uma solicitação como lidas
     */
    private function markRequestNotificationsAsRead($requestId, $userId)
    {
        $link = base_path("solicitacoes-reagendamento/{$requestId}");
        
        // Buscar notificações do tipo reschedule_request que apontam para esta solicitação
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = ? 
            AND type = 'reschedule_request' 
            AND link = ?
            AND is_read = 0
        ");
        $stmt->execute([$userId, $link]);
    }

    /**
     * ADMIN/SECRETARIA: Visualizar solicitação específica
     */
    public function show($id)
    {
        $currentRole = $_SESSION['current_role'] ?? '';
        
        // Apenas ADMIN e SECRETARIA podem visualizar solicitações
        if ($currentRole !== Constants::ROLE_ADMIN && $currentRole !== Constants::ROLE_SECRETARIA) {
            $_SESSION['error'] = 'Você não tem permissão para acessar este módulo.';
            redirect(base_url('dashboard'));
        }

        $request = $this->requestModel->findWithDetails($id);
        if (!$request) {
            $_SESSION['error'] = 'Solicitação não encontrada.';
            redirect(base_url('solicitacoes-reagendamento'));
        }

        $userId = $_SESSION['user_id'] ?? null;
        
        // Marcar notificação como lida ao visualizar (se houver)
        if ($userId) {
            $link = base_path("solicitacoes-reagendamento/{$id}");
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? 
                AND type = 'reschedule_request' 
                AND link = ?
                AND is_read = 0
            ");
            $stmt->execute([$userId, $link]);
        }

        $data = [
            'pageTitle' => 'Solicitação de Reagendamento',
            'request' => $request
        ];

        $this->view('reschedule_requests/show', $data);
    }
}
