<?php

namespace App\Controllers;

use App\Models\Setting;
use App\Models\TheoryDiscipline;
use App\Models\TheoryCourse;
use App\Models\TheoryCourseDiscipline;
use App\Models\Cfc;
use App\Models\CfcPixAccount;
use App\Services\PermissionService;
use App\Services\EmailService;
use App\Services\AuditService;
use App\Helpers\PwaIconGenerator;
use App\Config\Constants;

class ConfiguracoesController extends Controller
{
    private $cfcId;
    private $auditService;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->auditService = new AuditService();
        
        // Apenas ADMIN pode acessar configurações
        if ($_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para acessar este módulo.';
            redirect(base_url('dashboard'));
        }
    }

    /**
     * Tela de configurações SMTP
     */
    public function smtp()
    {
        $settingModel = new Setting();
        $settings = $settingModel->findByCfc($this->cfcId);

        $data = [
            'pageTitle' => 'Configurações SMTP',
            'settings' => $settings
        ];

        $this->view('configuracoes/smtp', $data);
    }

    /**
     * Salva configurações SMTP
     */
    public function salvarSmtp()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/smtp'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/smtp'));
        }

        $host = trim($_POST['host'] ?? '');
        $port = (int)($_POST['port'] ?? 587);
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $encryption = $_POST['encryption'] ?? 'tls';
        $fromEmail = trim($_POST['from_email'] ?? '');
        $fromName = trim($_POST['from_name'] ?? '');

        // Validações
        if (empty($host) || empty($username) || empty($fromEmail)) {
            $_SESSION['error'] = 'Preencha todos os campos obrigatórios.';
            redirect(base_url('configuracoes/smtp'));
        }

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail remetente inválido.';
            redirect(base_url('configuracoes/smtp'));
        }

        if (!in_array($encryption, ['tls', 'ssl', 'none'])) {
            $encryption = 'tls';
        }

        // Se senha não foi informada e já existe configuração, manter a atual
        if (empty($password) && $settings) {
            $encryptedPassword = $settings['password']; // Já está criptografada
        } else {
            // Criptografar senha (usar base64 simples por enquanto, ideal seria usar openssl)
            $encryptedPassword = base64_encode($password);
        }

        $settingModel = new Setting();
        $data = [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'password' => $encryptedPassword,
            'encryption' => $encryption,
            'from_email' => $fromEmail,
            'from_name' => $fromName
        ];

        if ($settingModel->save($this->cfcId, $data)) {
            $_SESSION['success'] = 'Configurações SMTP salvas com sucesso!';
        } else {
            $_SESSION['error'] = 'Erro ao salvar configurações.';
        }

        redirect(base_url('configuracoes/smtp'));
    }

    /**
     * Testa envio de e-mail
     */
    public function testarSmtp()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/smtp'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/smtp'));
        }

        $testEmail = trim($_POST['test_email'] ?? '');

        if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail de teste inválido.';
            redirect(base_url('configuracoes/smtp'));
        }

        try {
            $emailService = new EmailService();
            $emailService->test($testEmail);
            $_SESSION['success'] = 'E-mail de teste enviado com sucesso! Verifique a caixa de entrada.';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao enviar e-mail de teste: ' . $e->getMessage();
        }

        redirect(base_url('configuracoes/smtp'));
    }

    // ============================================
    // MÓDULO: CURSO TEÓRICO - CONFIGURAÇÕES
    // ============================================

    /**
     * Lista disciplinas
     */
    public function disciplinas()
    {
        $disciplineModel = new TheoryDiscipline();
        $disciplines = $disciplineModel->findByCfc($this->cfcId);

        $data = [
            'pageTitle' => 'Disciplinas Teóricas',
            'disciplines' => $disciplines
        ];

        $this->view('configuracoes/disciplinas/index', $data);
    }

    /**
     * Formulário nova disciplina
     */
    public function disciplinaNovo()
    {
        $data = [
            'pageTitle' => 'Nova Disciplina',
            'discipline' => null
        ];
        $this->view('configuracoes/disciplinas/form', $data);
    }

    /**
     * Criar disciplina
     */
    public function disciplinaCriar()
    {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/disciplinas/novo'));
        }

        $name = trim($_POST['name'] ?? '');
        $lessonsCount = !empty($_POST['default_lessons_count']) ? (int)$_POST['default_lessons_count'] : null;
        $lessonMinutes = !empty($_POST['default_lesson_minutes']) ? (int)$_POST['default_lesson_minutes'] : 50;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name)) {
            $_SESSION['error'] = 'Nome da disciplina é obrigatório.';
            redirect(base_url('configuracoes/disciplinas/novo'));
        }

        // Validar quantidade de aulas
        if ($lessonsCount !== null && $lessonsCount <= 0) {
            $_SESSION['error'] = 'A quantidade de aulas deve ser maior que zero.';
            redirect(base_url('configuracoes/disciplinas/novo'));
        }

        // Validar minutos por aula
        if ($lessonMinutes <= 0 || $lessonMinutes > 180) {
            $_SESSION['error'] = 'Minutos por aula deve estar entre 1 e 180.';
            redirect(base_url('configuracoes/disciplinas/novo'));
        }

        // Calcular total de minutos (backend sempre recalcula)
        $defaultMinutes = null;
        if ($lessonsCount !== null && $lessonsCount > 0) {
            $defaultMinutes = $lessonsCount * $lessonMinutes;
        }

        $disciplineModel = new TheoryDiscipline();
        $data = [
            'cfc_id' => $this->cfcId,
            'name' => $name,
            'default_minutes' => $defaultMinutes,
            'default_lessons_count' => $lessonsCount,
            'default_lesson_minutes' => $lessonMinutes,
            'sort_order' => $sortOrder,
            'active' => $active
        ];

        $id = $disciplineModel->create($data);
        $this->auditService->logCreate('theory_disciplines', $id, $data);

        $_SESSION['success'] = 'Disciplina criada com sucesso!';
        redirect(base_url('configuracoes/disciplinas'));
    }

    /**
     * Formulário editar disciplina
     */
    public function disciplinaEditar($id)
    {
        $disciplineModel = new TheoryDiscipline();
        $discipline = $disciplineModel->find($id);

        if (!$discipline || $discipline['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Disciplina não encontrada.';
            redirect(base_url('configuracoes/disciplinas'));
        }

        $data = [
            'pageTitle' => 'Editar Disciplina',
            'discipline' => $discipline
        ];
        $this->view('configuracoes/disciplinas/form', $data);
    }

    /**
     * Atualizar disciplina
     */
    public function disciplinaAtualizar($id)
    {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("configuracoes/disciplinas/{$id}/editar"));
        }

        $disciplineModel = new TheoryDiscipline();
        $discipline = $disciplineModel->find($id);

        if (!$discipline || $discipline['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Disciplina não encontrada.';
            redirect(base_url('configuracoes/disciplinas'));
        }

        $name = trim($_POST['name'] ?? '');
        $lessonsCount = !empty($_POST['default_lessons_count']) ? (int)$_POST['default_lessons_count'] : null;
        $lessonMinutes = !empty($_POST['default_lesson_minutes']) ? (int)$_POST['default_lesson_minutes'] : 50;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;

        if (empty($name)) {
            $_SESSION['error'] = 'Nome da disciplina é obrigatório.';
            redirect(base_url("configuracoes/disciplinas/{$id}/editar"));
        }

        // Validar quantidade de aulas
        if ($lessonsCount !== null && $lessonsCount <= 0) {
            $_SESSION['error'] = 'A quantidade de aulas deve ser maior que zero.';
            redirect(base_url("configuracoes/disciplinas/{$id}/editar"));
        }

        // Validar minutos por aula
        if ($lessonMinutes <= 0 || $lessonMinutes > 180) {
            $_SESSION['error'] = 'Minutos por aula deve estar entre 1 e 180.';
            redirect(base_url("configuracoes/disciplinas/{$id}/editar"));
        }

        // Calcular total de minutos (backend sempre recalcula)
        $defaultMinutes = null;
        if ($lessonsCount !== null && $lessonsCount > 0) {
            $defaultMinutes = $lessonsCount * $lessonMinutes;
        }

        $dataBefore = $discipline;
        $data = [
            'name' => $name,
            'default_minutes' => $defaultMinutes,
            'default_lessons_count' => $lessonsCount,
            'default_lesson_minutes' => $lessonMinutes,
            'sort_order' => $sortOrder,
            'active' => $active
        ];

        $disciplineModel->update($id, $data);
        $this->auditService->logUpdate('theory_disciplines', $id, $dataBefore, array_merge($discipline, $data));

        $_SESSION['success'] = 'Disciplina atualizada com sucesso!';
        redirect(base_url('configuracoes/disciplinas'));
    }

    /**
     * Excluir disciplina e todos os dados relacionados
     */
    public function disciplinaExcluir($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/disciplinas'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/disciplinas'));
        }

        $disciplineModel = new TheoryDiscipline();
        $discipline = $disciplineModel->find($id);

        if (!$discipline || $discipline['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Disciplina não encontrada.';
            redirect(base_url('configuracoes/disciplinas'));
        }

        // Salvar dados para auditoria
        $dataBefore = $discipline;

        try {
            // 1. Deletar todas as sessões teóricas relacionadas
            $sessions = $this->query(
                "SELECT id FROM theory_sessions WHERE discipline_id = ?",
                [$id]
            )->fetchAll();
            
            foreach ($sessions as $session) {
                // Deletar presenças relacionadas (tem CASCADE, mas garantindo)
                $this->query(
                    "DELETE FROM theory_attendance WHERE session_id = ?",
                    [$session['id']]
                );
                // Deletar sessão
                $this->query(
                    "DELETE FROM theory_sessions WHERE id = ?",
                    [$session['id']]
                );
            }

            // 2. Deletar relações com cursos (já tem CASCADE, mas garantindo)
            $this->query(
                "DELETE FROM theory_course_disciplines WHERE discipline_id = ?",
                [$id]
            );

            // 3. Registrar auditoria antes de deletar
            $this->auditService->logDelete('theory_disciplines', $id, $dataBefore);

            // 4. Deletar a disciplina
            $disciplineModel->delete($id);

            $_SESSION['success'] = 'Disciplina e todos os dados relacionados foram excluídos com sucesso!';
        } catch (\Exception $e) {
            error_log("Erro ao excluir disciplina: " . $e->getMessage());
            $_SESSION['error'] = 'Erro ao excluir disciplina: ' . $e->getMessage();
        }

        redirect(base_url('configuracoes/disciplinas'));
    }

    /**
     * Método auxiliar para executar queries diretas
     */
    private function query($sql, $params = [])
    {
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Lista cursos
     */
    public function cursos()
    {
        $courseModel = new TheoryCourse();
        $courses = $courseModel->findActiveByCfc($this->cfcId);

        $data = [
            'pageTitle' => 'Cursos Teóricos',
            'courses' => $courses
        ];

        $this->view('configuracoes/cursos/index', $data);
    }

    /**
     * Formulário novo curso
     */
    public function cursoNovo()
    {
        $disciplineModel = new TheoryDiscipline();
        $disciplines = $disciplineModel->findActiveByCfc($this->cfcId);

        $data = [
            'pageTitle' => 'Novo Curso Teórico',
            'course' => null,
            'disciplines' => $disciplines,
            'courseDisciplines' => []
        ];
        $this->view('configuracoes/cursos/form', $data);
    }

    /**
     * Criar curso
     */
    public function cursoCriar()
    {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cursos/novo'));
        }

        $name = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $disciplines = $_POST['disciplines'] ?? [];

        if (empty($name)) {
            $_SESSION['error'] = 'Nome do curso é obrigatório.';
            redirect(base_url('configuracoes/cursos/novo'));
        }

        $courseModel = new TheoryCourse();
        $courseDisciplineModel = new TheoryCourseDiscipline();

        $courseData = [
            'cfc_id' => $this->cfcId,
            'name' => $name,
            'active' => $active
        ];

        $courseId = $courseModel->create($courseData);
        $this->auditService->logCreate('theory_courses', $courseId, $courseData);

        // Vincular disciplinas
        if (!empty($disciplines)) {
            foreach ($disciplines as $index => $disciplineData) {
                if (empty($disciplineData['discipline_id'])) continue;

                // Processar campos de aulas
                $lessonsCount = !empty($disciplineData['lessons_count']) ? (int)$disciplineData['lessons_count'] : null;
                $lessonMinutes = !empty($disciplineData['lesson_minutes']) ? (int)$disciplineData['lesson_minutes'] : 50;

                // Validar
                if ($lessonsCount !== null && $lessonsCount <= 0) {
                    $_SESSION['error'] = 'A quantidade de aulas deve ser maior que zero.';
                    redirect(base_url('configuracoes/cursos/novo'));
                }

                if ($lessonMinutes <= 0 || $lessonMinutes > 180) {
                    $_SESSION['error'] = 'Minutos por aula deve estar entre 1 e 180.';
                    redirect(base_url('configuracoes/cursos/novo'));
                }

                // Calcular minutos totais (backend sempre recalcula)
                $minutes = null;
                if ($lessonsCount !== null && $lessonsCount > 0) {
                    $minutes = $lessonsCount * $lessonMinutes;
                } elseif (!empty($disciplineData['minutes'])) {
                    // Fallback: se minutes veio direto (compatibilidade com registros antigos)
                    $minutes = (int)$disciplineData['minutes'];
                }

                $courseDisciplineModel->create([
                    'course_id' => $courseId,
                    'discipline_id' => (int)$disciplineData['discipline_id'],
                    'minutes' => $minutes,
                    'lessons_count' => $lessonsCount,
                    'lesson_minutes' => $lessonMinutes,
                    'sort_order' => (int)$index,
                    'required' => isset($disciplineData['required']) ? 1 : 0
                ]);
            }
        }

        $_SESSION['success'] = 'Curso criado com sucesso!';
        redirect(base_url('configuracoes/cursos'));
    }

    /**
     * Formulário editar curso
     */
    public function cursoEditar($id)
    {
        $courseModel = new TheoryCourse();
        $course = $courseModel->findWithDisciplines($id);

        if (!$course || $course['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Curso não encontrado.';
            redirect(base_url('configuracoes/cursos'));
        }

        $disciplineModel = new TheoryDiscipline();
        $disciplines = $disciplineModel->findActiveByCfc($this->cfcId);

        // Processar courseDisciplines para inferir lessons_count/lesson_minutes se não existirem
        $courseDisciplines = $course['disciplines'] ?? [];
        foreach ($courseDisciplines as &$cd) {
            if (empty($cd['lessons_count']) && !empty($cd['minutes'])) {
                $cd['lesson_minutes'] = $cd['lesson_minutes'] ?? 50;
                $cd['lessons_count'] = ceil($cd['minutes'] / $cd['lesson_minutes']);
            }
        }

        $data = [
            'pageTitle' => 'Editar Curso Teórico',
            'course' => $course,
            'disciplines' => $disciplines,
            'courseDisciplines' => $courseDisciplines
        ];
        $this->view('configuracoes/cursos/form', $data);
    }

    /**
     * Atualizar curso
     */
    public function cursoAtualizar($id)
    {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("configuracoes/cursos/{$id}/editar"));
        }

        $courseModel = new TheoryCourse();
        $course = $courseModel->find($id);

        if (!$course || $course['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Curso não encontrado.';
            redirect(base_url('configuracoes/cursos'));
        }

        $name = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;
        $disciplines = $_POST['disciplines'] ?? [];

        if (empty($name)) {
            $_SESSION['error'] = 'Nome do curso é obrigatório.';
            redirect(base_url("configuracoes/cursos/{$id}/editar"));
        }

        $courseDisciplineModel = new TheoryCourseDiscipline();
        $dataBefore = $course;

        $courseData = [
            'name' => $name,
            'active' => $active
        ];

        $courseModel->update($id, $courseData);
        $this->auditService->logUpdate('theory_courses', $id, $dataBefore, array_merge($course, $courseData));

        // Remover disciplinas antigas e adicionar novas
        $courseDisciplineModel->deleteByCourse($id);

        if (!empty($disciplines)) {
            foreach ($disciplines as $index => $disciplineData) {
                if (empty($disciplineData['discipline_id'])) continue;

                // Processar campos de aulas
                $lessonsCount = !empty($disciplineData['lessons_count']) ? (int)$disciplineData['lessons_count'] : null;
                $lessonMinutes = !empty($disciplineData['lesson_minutes']) ? (int)$disciplineData['lesson_minutes'] : 50;

                // Validar
                if ($lessonsCount !== null && $lessonsCount <= 0) {
                    $_SESSION['error'] = 'A quantidade de aulas deve ser maior que zero.';
                    redirect(base_url("configuracoes/cursos/{$id}/editar"));
                }

                if ($lessonMinutes <= 0 || $lessonMinutes > 180) {
                    $_SESSION['error'] = 'Minutos por aula deve estar entre 1 e 180.';
                    redirect(base_url("configuracoes/cursos/{$id}/editar"));
                }

                // Calcular minutos totais (backend sempre recalcula)
                $minutes = null;
                if ($lessonsCount !== null && $lessonsCount > 0) {
                    $minutes = $lessonsCount * $lessonMinutes;
                } elseif (!empty($disciplineData['minutes'])) {
                    // Fallback: se minutes veio direto (compatibilidade com registros antigos)
                    $minutes = (int)$disciplineData['minutes'];
                }

                $courseDisciplineModel->create([
                    'course_id' => $id,
                    'discipline_id' => (int)$disciplineData['discipline_id'],
                    'minutes' => $minutes,
                    'lessons_count' => $lessonsCount,
                    'lesson_minutes' => $lessonMinutes,
                    'sort_order' => (int)$index,
                    'required' => isset($disciplineData['required']) ? 1 : 0
                ]);
            }
        }

        $_SESSION['success'] = 'Curso atualizado com sucesso!';
        redirect(base_url('configuracoes/cursos'));
    }

    /**
     * Excluir curso e todos os dados relacionados
     */
    public function cursoExcluir($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cursos'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cursos'));
        }

        $courseModel = new TheoryCourse();
        $course = $courseModel->find($id);

        if (!$course || $course['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Curso não encontrado.';
            redirect(base_url('configuracoes/cursos'));
        }

        // Salvar dados para auditoria
        $dataBefore = $course;

        try {
            // 1. Deletar todas as turmas relacionadas (isso deletará automaticamente sessões, matrículas e presenças)
            $classes = $this->query(
                "SELECT id FROM theory_classes WHERE course_id = ?",
                [$id]
            )->fetchAll();
            
            foreach ($classes as $class) {
                // Deletar presenças (através das sessões)
                $sessions = $this->query(
                    "SELECT id FROM theory_sessions WHERE class_id = ?",
                    [$class['id']]
                )->fetchAll();
                
                foreach ($sessions as $session) {
                    $this->query(
                        "DELETE FROM theory_attendance WHERE session_id = ?",
                        [$session['id']]
                    );
                }
                
                // Deletar sessões
                $this->query(
                    "DELETE FROM theory_sessions WHERE class_id = ?",
                    [$class['id']]
                );
                
                // Deletar matrículas (já tem CASCADE, mas garantindo)
                $this->query(
                    "DELETE FROM theory_enrollments WHERE class_id = ?",
                    [$class['id']]
                );
                
                // Deletar turma
                $this->query(
                    "DELETE FROM theory_classes WHERE id = ?",
                    [$class['id']]
                );
            }

            // 2. Deletar relações com disciplinas (já tem CASCADE, mas garantindo)
            $this->query(
                "DELETE FROM theory_course_disciplines WHERE course_id = ?",
                [$id]
            );

            // 3. Atualizar matrículas para remover referência ao curso (ON DELETE SET NULL)
            $this->query(
                "UPDATE enrollments SET theory_course_id = NULL WHERE theory_course_id = ?",
                [$id]
            );

            // 4. Registrar auditoria antes de deletar
            $this->auditService->logDelete('theory_courses', $id, $dataBefore);

            // 5. Deletar o curso
            $courseModel->delete($id);

            $_SESSION['success'] = 'Curso e todos os dados relacionados foram excluídos com sucesso!';
        } catch (\Exception $e) {
            error_log("Erro ao excluir curso: " . $e->getMessage());
            $_SESSION['error'] = 'Erro ao excluir curso: ' . $e->getMessage();
        }

        redirect(base_url('configuracoes/cursos'));
    }

    // ============================================
    // MÓDULO: CONFIGURAÇÕES DO CFC (LOGO PWA)
    // ============================================

    /**
     * Tela de configurações do CFC (logo para PWA)
     */
    public function cfc()
    {
        // FASE 8: Log de exibição (não só de upload)
        $logFile = dirname(__DIR__, 2) . '/storage/logs/display_logo.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();
        
        $hasLogo = !empty($cfc['logo_path']);
        $logoPath = $cfc['logo_path'] ?? null;
        $logoUrl = null;
        $fileExists = false;
        
        if ($hasLogo && $logoPath) {
            $filepath = dirname(__DIR__, 2) . '/' . $logoPath;
            $fileExists = file_exists($filepath);
            
            // FASE 7: URL com cache buster baseado em updated_at
            $cacheBuster = $cfc['updated_at'] ? strtotime($cfc['updated_at']) : time();
            $logoUrl = base_path('configuracoes/cfc/logo') . '?v=' . $cacheBuster;
        }
        
        // Log de exibição
        $displayLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'cfc_id' => $cfc['id'] ?? 'N/A',
            'logo_path' => $logoPath,
            'hasLogo' => $hasLogo,
            'fileExists' => $fileExists,
            'logoUrl' => $logoUrl,
            'docroot' => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'script_dir' => __DIR__,
            'project_root' => dirname(__DIR__, 2)
        ];
        @file_put_contents($logFile, "=== DISPLAY LOGO ===\n" . json_encode($displayLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

        // Buscar contas PIX do CFC (com tratamento de erro caso tabela não exista ainda)
        $pixAccounts = [];
        try {
            $pixAccountModel = new CfcPixAccount();
            $pixAccounts = $pixAccountModel->findByCfc($cfc['id'], false); // false = incluir inativas
        } catch (\Exception $e) {
            // Se a tabela não existir ainda (migrations não executadas), usar array vazio
            // Isso permite que a página carregue mesmo sem as migrations
            error_log("ConfiguracoesController::cfc() - Erro ao buscar contas PIX (tabela pode não existir ainda): " . $e->getMessage());
            $pixAccounts = [];
        }

        $data = [
            'pageTitle' => 'Configurações do CFC',
            'cfc' => $cfc,
            'hasLogo' => $hasLogo,
            'logoUrl' => $logoUrl,
            'fileExists' => $fileExists,
            'iconsExist' => $cfc ? PwaIconGenerator::iconsExist($cfc['id']) : false,
            'pixAccounts' => $pixAccounts
        ];

        $this->view('configuracoes/cfc', $data);
    }

    /**
     * Upload de logo do CFC
     */
    public function uploadLogo()
    {
        // FASE 4: Log OBRIGATÓRIO na PRIMEIRA LINHA - antes de QUALQUER coisa
        $logFile = dirname(__DIR__, 2) . '/storage/logs/upload_logo.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Log detalhado na primeira linha
        $initialLog = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method_called' => 'uploadLogo',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
            'POST_keys' => array_keys($_POST),
            'FILES_keys' => array_keys($_FILES),
            'FILES_count' => count($_FILES),
            'has_logo_in_FILES' => isset($_FILES['logo']),
            'FILES_logo' => isset($_FILES['logo']) ? [
                'name' => $_FILES['logo']['name'] ?? 'N/A',
                'type' => $_FILES['logo']['type'] ?? 'N/A',
                'size' => $_FILES['logo']['size'] ?? 0,
                'error' => $_FILES['logo']['error'] ?? 'N/A',
                'tmp_name' => $_FILES['logo']['tmp_name'] ?? 'N/A'
            ] : 'N/A',
            'csrf_token_present' => isset($_POST['csrf_token'])
        ];
        @file_put_contents($logFile, "=== UPLOAD REQUEST START ===\n" . json_encode($initialLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        // Headers de debug para console do navegador
        header('X-Upload-Debug: method_called=uploadLogo');
        header('X-Upload-Debug-Files: ' . count($_FILES));
        header('X-Upload-Debug-HasLogo: ' . (isset($_FILES['logo']) ? 'yes' : 'no'));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            @file_put_contents($logFile, "ERROR: Request method is not POST\n", FILE_APPEND);
            header('X-Upload-Debug-Error: Request method is not POST');
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            @file_put_contents($logFile, "ERROR: CSRF token invalid\n", FILE_APPEND);
            header('X-Upload-Debug-Error: CSRF token invalid');
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            @file_put_contents($logFile, "ERROR: CFC não encontrado\n", FILE_APPEND);
            header('X-Upload-Debug-Error: CFC não encontrado');
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        if (!isset($_FILES['logo'])) {
            $errorLog = [
                'error' => 'Nenhum arquivo foi enviado',
                'FILES_keys' => array_keys($_FILES),
                'POST_keys' => array_keys($_POST),
                'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A'
            ];
            @file_put_contents($logFile, "=== UPLOAD ERROR (no file) ===\n" . json_encode($errorLog, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            header('X-Upload-Debug-Error: Nenhum arquivo foi enviado');
            header('X-Upload-Debug-FilesKeys: ' . implode(',', array_keys($_FILES)));
            $_SESSION['error'] = 'Nenhum arquivo foi enviado.';
            redirect(base_url('configuracoes/cfc'));
        }

        $file = $_FILES['logo'];
        
        // DEBUG DETERMINÍSTICO: Log completo de $_FILES
        $logFile = dirname(__DIR__, 2) . '/storage/logs/upload_logo.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Log completo de $_FILES (print_r para ver estrutura exata)
        $filesDebug = [
            '_FILES_complete' => print_r($_FILES, true),
            '_FILES_keys' => array_keys($_FILES),
            '_FILES_logo_raw' => $file,
            'php_upload_config' => [
                'file_uploads' => ini_get('file_uploads'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
                'upload_tmp_dir_exists' => is_dir(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
                'upload_tmp_dir_writable' => is_writable(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
                'max_file_uploads' => ini_get('max_file_uploads'),
                'memory_limit' => ini_get('memory_limit')
            ]
        ];
        @file_put_contents($logFile, "=== DEBUG: $_FILES COMPLETO ===\n" . json_encode($filesDebug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        // Mapeamento completo dos UPLOAD_ERR_*
        $uploadErrorsMap = [
            UPLOAD_ERR_OK => 'OK - Nenhum erro',
            UPLOAD_ERR_INI_SIZE => 'Arquivo excede upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'Arquivo excede MAX_FILE_SIZE do formulário',
            UPLOAD_ERR_PARTIAL => 'Upload parcial - arquivo não foi completamente transferido',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado (upload_tmp_dir)',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao escrever arquivo no disco (permissões ou espaço)',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão do PHP'
        ];
        
        // Verificar erro de upload com log detalhado
        $errorCode = $file['error'];
        $errorInfo = [
            'error_code' => $errorCode,
            'error_message' => $uploadErrorsMap[$errorCode] ?? 'Erro desconhecido (código: ' . $errorCode . ')',
            'error_constant' => array_search($errorCode, [
                UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
                UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
                UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
                UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
                UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
                UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
                UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
                UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION'
            ]) ?: 'UNKNOWN'
        ];
        @file_put_contents($logFile, "=== DEBUG: UPLOAD ERROR ANALYSIS ===\n" . json_encode($errorInfo, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errorMsg = $uploadErrorsMap[$errorCode] ?? 'Erro desconhecido no upload (código: ' . $errorCode . ').';
            @file_put_contents($logFile, "=== UPLOAD ERROR: REDIRECTING ===\n" . json_encode(['error' => $errorMsg, 'error_code' => $errorCode], JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = $errorMsg;
            redirect(base_url('configuracoes/cfc'));
        }
        
        // DEBUG: Validação detalhada do tmp_name ANTES do move
        $tmpNameDebug = [
            'tmp_name' => $file['tmp_name'],
            'tmp_name_exists' => file_exists($file['tmp_name']),
            'tmp_name_readable' => is_readable($file['tmp_name']),
            'tmp_name_size' => file_exists($file['tmp_name']) ? filesize($file['tmp_name']) : 0,
            'tmp_name_size_reported' => $file['size'],
            'is_uploaded_file' => is_uploaded_file($file['tmp_name']),
            'tmp_dir' => dirname($file['tmp_name']),
            'tmp_dir_exists' => is_dir(dirname($file['tmp_name'])),
            'tmp_dir_writable' => is_writable(dirname($file['tmp_name']))
        ];
        @file_put_contents($logFile, "=== DEBUG: tmp_name VALIDATION ===\n" . json_encode($tmpNameDebug, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        // Se tmp_name não existe ou não é um arquivo enviado, erro crítico
        if (!file_exists($file['tmp_name'])) {
            $criticalError = [
                'error' => 'tmp_name não existe no disco',
                'tmp_name' => $file['tmp_name'],
                'lastError' => error_get_last(),
                'tmpNameDebug' => $tmpNameDebug
            ];
            @file_put_contents($logFile, "=== CRITICAL ERROR: tmp_name não existe ===\n" . json_encode($criticalError, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = 'Arquivo temporário não encontrado. Verifique configurações do PHP (upload_tmp_dir).';
            redirect(base_url('configuracoes/cfc'));
        }
        
        if (!is_uploaded_file($file['tmp_name'])) {
            $criticalError = [
                'error' => 'tmp_name não é um arquivo enviado via POST (possível ataque)',
                'tmp_name' => $file['tmp_name'],
                'lastError' => error_get_last(),
                'tmpNameDebug' => $tmpNameDebug
            ];
            @file_put_contents($logFile, "=== CRITICAL ERROR: tmp_name não é uploaded file ===\n" . json_encode($criticalError, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = 'Arquivo inválido. Tente novamente.';
            redirect(base_url('configuracoes/cfc'));
        }
        
        // Validar tipo
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $_SESSION['error'] = 'Tipo de arquivo inválido. Use JPG, PNG ou WEBP.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Validar tamanho (5MB - logo pode ser maior que foto)
        if ($file['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = 'Arquivo muito grande. Máximo 5MB.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Criar diretório se não existir
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/cfcs/';
        $uploadDirDebug = [
            'uploadDir' => $uploadDir,
            'uploadDirExists' => is_dir($uploadDir),
            'uploadDirWritable' => is_dir($uploadDir) ? is_writable($uploadDir) : false,
            'parentDir' => dirname($uploadDir),
            'parentDirExists' => is_dir(dirname($uploadDir)),
            'parentDirWritable' => is_dir(dirname($uploadDir)) ? is_writable(dirname($uploadDir)) : false,
            'diskFreeSpace' => disk_free_space($uploadDir),
            'fileSize' => $file['size'],
            'hasEnoughSpace' => disk_free_space($uploadDir) >= $file['size']
        ];
        
        if (!is_dir($uploadDir)) {
            $mkdirResult = @mkdir($uploadDir, 0755, true);
            $uploadDirDebug['mkdirAttempted'] = true;
            $uploadDirDebug['mkdirResult'] = $mkdirResult;
            $uploadDirDebug['mkdirError'] = error_get_last();
            $uploadDirDebug['uploadDirExistsAfterMkdir'] = is_dir($uploadDir);
            
            if (!$mkdirResult || !is_dir($uploadDir)) {
                @file_put_contents($logFile, "=== ERROR: Não foi possível criar uploadDir ===\n" . json_encode($uploadDirDebug, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
                $_SESSION['error'] = 'Erro ao criar diretório de upload. Verifique as permissões.';
                redirect(base_url('configuracoes/cfc'));
            }
        }
        
        @file_put_contents($logFile, "=== DEBUG: uploadDir VALIDATION ===\n" . json_encode($uploadDirDebug, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        // Verificar se diretório é gravável
        if (!is_writable($uploadDir)) {
            $writableError = [
                'error' => 'Diretório não é gravável',
                'uploadDir' => $uploadDir,
                'uploadDirExists' => is_dir($uploadDir),
                'uploadDirWritable' => is_writable($uploadDir),
                'permissions' => substr(sprintf('%o', fileperms($uploadDir)), -4),
                'lastError' => error_get_last()
            ];
            @file_put_contents($logFile, "=== ERROR: uploadDir não é gravável ===\n" . json_encode($writableError, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = 'Diretório de upload não tem permissão de escrita. Verifique as permissões.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Gerar nome único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'cfc_' . $cfc['id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        // Remover logo antigo se existir
        if (!empty($cfc['logo_path'])) {
            $oldPath = dirname(__DIR__, 2) . '/' . $cfc['logo_path'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            // Remover ícones PWA antigos
            PwaIconGenerator::removeIcons($cfc['id']);
        }

        // Log detalhado ANTES do move
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'file_info' => [
                'name' => $file['name'],
                'type' => $file['type'],
                'size' => $file['size'],
                'error' => $file['error'],
                'tmp_name' => $file['tmp_name']
            ],
            'destination' => [
                'uploadDir' => $uploadDir,
                'filename' => $filename,
                'filepath' => $filepath,
                'filepathExists' => file_exists($filepath),
                'filepathWritable' => is_writable(dirname($filepath))
            ],
            'tmp_validation' => [
                'tmpNameExists' => file_exists($file['tmp_name']),
                'tmpNameReadable' => is_readable($file['tmp_name']),
                'tmpNameSize' => file_exists($file['tmp_name']) ? filesize($file['tmp_name']) : 0,
                'isUploadedFile' => is_uploaded_file($file['tmp_name'])
            ],
            'cfcId' => $cfc['id']
        ];
        @file_put_contents($logFile, "=== UPLOAD: ANTES DO move_uploaded_file ===\n" . json_encode($logData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

        // Limpar error_get_last() antes do move para capturar erro específico
        error_clear_last();
        
        // Mover arquivo
        $moveResult = move_uploaded_file($file['tmp_name'], $filepath);
        $moveError = error_get_last();
        
        // Log detalhado APÓS o move
        $moveDebug = [
            'moveResult' => $moveResult,
            'fileExistsAfterMove' => file_exists($filepath),
            'fileSizeAfterMove' => file_exists($filepath) ? filesize($filepath) : 0,
            'tmpNameExistsAfterMove' => file_exists($file['tmp_name']),
            'lastError' => $moveError,
            'destination' => [
                'filepath' => $filepath,
                'filepathExists' => file_exists($filepath),
                'filepathReadable' => file_exists($filepath) ? is_readable($filepath) : false,
                'filepathWritable' => file_exists($filepath) ? is_writable($filepath) : false
            ]
        ];
        @file_put_contents($logFile, "=== UPLOAD: APÓS move_uploaded_file ===\n" . json_encode($moveDebug, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        if (!$moveResult) {
            $errorMsg = 'Erro ao salvar arquivo.';
            $errorDetails = [];
            
            // Verificar tipo de erro
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMsg = $uploadErrorsMap[$file['error']] ?? 'Erro desconhecido no upload.';
                $errorDetails['uploadError'] = $file['error'];
            } else {
                // Erro adicional: verificar permissões e espaço em disco
                $errorDetails['checks'] = [
                    'uploadDirWritable' => is_writable($uploadDir),
                    'diskFreeSpace' => disk_free_space($uploadDir),
                    'fileSize' => $file['size'],
                    'hasEnoughSpace' => disk_free_space($uploadDir) >= $file['size'],
                    'filepathParentWritable' => is_writable(dirname($filepath))
                ];
                
                if (!is_writable($uploadDir)) {
                    $errorMsg .= ' Diretório não é gravável.';
                }
                if (disk_free_space($uploadDir) < $file['size']) {
                    $errorMsg .= ' Espaço em disco insuficiente.';
                }
                if (!is_writable(dirname($filepath))) {
                    $errorMsg .= ' Diretório pai do arquivo não é gravável.';
                }
            }
            
            // Log do erro com TODOS os detalhes
            $errorLog = [
                'error' => $errorMsg,
                'errorDetails' => $errorDetails,
                'lastError' => $moveError,
                'moveDebug' => $moveDebug,
                'logData' => $logData
            ];
            @file_put_contents($logFile, "=== UPLOAD ERROR: move_uploaded_file FALHOU ===\n" . json_encode($errorLog, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = $errorMsg;
            redirect(base_url('configuracoes/cfc'));
        }

        // Verificar se arquivo foi salvo corretamente
        if (!file_exists($filepath)) {
            $fileNotExistsError = [
                'error' => 'Arquivo não existe após move_uploaded_file retornar TRUE',
                'filepath' => $filepath,
                'filepathExists' => file_exists($filepath),
                'moveResult' => $moveResult,
                'lastError' => error_get_last(),
                'moveDebug' => $moveDebug
            ];
            @file_put_contents($logFile, "=== CRITICAL ERROR: Arquivo não existe após move ===\n" . json_encode($fileNotExistsError, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = 'Arquivo não foi salvo corretamente.';
            redirect(base_url('configuracoes/cfc'));
        }

        // VALIDAÇÃO FINAL: Só atualizar DB se moveResult foi TRUE e arquivo existe
        $finalValidation = [
            'moveResult' => $moveResult,
            'fileExists' => file_exists($filepath),
            'fileSize' => filesize($filepath),
            'fileSizeMatches' => filesize($filepath) === $file['size'],
            'canProceedToDbUpdate' => ($moveResult === true && file_exists($filepath))
        ];
        @file_put_contents($logFile, "=== VALIDAÇÃO FINAL: Antes de atualizar DB ===\n" . json_encode($finalValidation, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        if (!$finalValidation['canProceedToDbUpdate']) {
            $criticalError = [
                'error' => 'Validação final falhou - não deve atualizar DB',
                'finalValidation' => $finalValidation,
                'moveDebug' => $moveDebug
            ];
            @file_put_contents($logFile, "=== CRITICAL ERROR: Validação final falhou ===\n" . json_encode($criticalError, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
            $_SESSION['error'] = 'Erro ao validar arquivo antes de atualizar banco de dados.';
            redirect(base_url('configuracoes/cfc'));
        }

        // FASE 7: Atualizar banco com log detalhado (SÓ SE VALIDAÇÃO PASSOU)
        $relativePath = 'storage/uploads/cfcs/' . $filename;
        
        // Log antes do update
        $dbLogBefore = [
            'cfc_id' => $cfc['id'],
            'cfc_id_source' => 'session: ' . ($_SESSION['cfc_id'] ?? 'N/A'),
            'update_data' => ['logo_path' => $relativePath],
            'sql_will_execute' => "UPDATE cfcs SET logo_path = ? WHERE id = ?"
        ];
        @file_put_contents($logFile, "=== DB UPDATE START ===\n" . json_encode($dbLogBefore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        try {
            $updateResult = $cfcModel->update($cfc['id'], ['logo_path' => $relativePath]);
            
            // Verificar se foi atualizado no banco
            $cfcAfterUpdate = $cfcModel->find($cfc['id']);
            
            if (!$cfcAfterUpdate) {
                throw new \Exception('CFC não encontrado após update');
            }
            
            $logData['dbUpdate'] = [
                'updateResult' => $updateResult,
                'relativePath' => $relativePath,
                'logoPathInDb' => $cfcAfterUpdate['logo_path'] ?? 'NULL',
                'dbUpdateSuccess' => ($cfcAfterUpdate['logo_path'] ?? null) === $relativePath,
                'cfc_id_used' => $cfc['id'],
                'cfc_id_after_update' => $cfcAfterUpdate['id'] ?? 'N/A'
            ];
            
            // Se o update não foi bem-sucedido, lançar exceção
            if (!$logData['dbUpdate']['dbUpdateSuccess']) {
                throw new \Exception('Update não foi bem-sucedido. Logo não foi salvo no banco de dados.');
            }
        } catch (\Exception $e) {
            $errorLog = [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'cfc_id' => $cfc['id'],
                'relativePath' => $relativePath
            ];
            @file_put_contents($logFile, "=== DB UPDATE ERROR ===\n" . json_encode($errorLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
            $_SESSION['error'] = 'Erro ao salvar logo no banco de dados: ' . $e->getMessage();
            redirect(base_url('configuracoes/cfc'));
        }
        
        // Log após update
        @file_put_contents($logFile, "=== DB UPDATE RESULT ===\n" . json_encode($logData['dbUpdate'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        // Log do sucesso
        $logData['success'] = true;
        $logData['fileExistsOnDisk'] = file_exists($filepath);
        $logData['fileSizeOnDisk'] = filesize($filepath);
        @file_put_contents($logFile, "=== UPLOAD SUCCESS ===\n" . json_encode($logData, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        
        // Headers de debug para sucesso
        header('X-Upload-Debug: success');
        header('X-Upload-Debug-FilePath: ' . $relativePath);
        header('X-Upload-Debug-FileSize: ' . filesize($filepath));
        header('X-Upload-Debug-DbUpdate: ' . ($logData['dbUpdate']['dbUpdateSuccess'] ? 'success' : 'failed'));

        // Gerar ícones PWA
        $icons = PwaIconGenerator::generateIcons($filepath, $cfc['id']);
        
        if ($icons) {
            $_SESSION['success'] = 'Logo atualizado e ícones PWA gerados com sucesso!';
        } else {
            $_SESSION['warning'] = 'Logo atualizado, mas houve erro ao gerar ícones PWA. Verifique se a extensão GD está habilitada.';
        }

        // Auditoria
        $this->auditService->log('upload_logo', 'cfcs', $cfc['id'], ['old_logo' => $cfc['logo_path'] ?? null], ['new_logo' => $relativePath]);

        redirect(base_url('configuracoes/cfc'));
    }

    /**
     * Remover logo do CFC
     */
    public function removerLogo()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Remover arquivo de logo
        if (!empty($cfc['logo_path'])) {
            $oldPath = dirname(__DIR__, 2) . '/' . $cfc['logo_path'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Remover ícones PWA
        PwaIconGenerator::removeIcons($cfc['id']);

        // Atualizar banco
        $cfcModel->update($cfc['id'], ['logo_path' => null]);

        // Auditoria
        $this->auditService->log('remove_logo', 'cfcs', $cfc['id'], ['old_logo' => $cfc['logo_path'] ?? null], []);

        $_SESSION['success'] = 'Logo removido com sucesso!';
        redirect(base_url('configuracoes/cfc'));
    }

    /**
     * Salvar informações do CFC (nome, CNPJ, etc)
     */
    public function salvarCfc()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        $nome = trim($_POST['nome'] ?? '');
        $cnpj = trim($_POST['cnpj'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Campos de endereço estruturado
        $endereco_logradouro = trim($_POST['endereco_logradouro'] ?? '');
        $endereco_numero = trim($_POST['endereco_numero'] ?? '');
        $endereco_complemento = trim($_POST['endereco_complemento'] ?? '');
        $endereco_bairro = trim($_POST['endereco_bairro'] ?? '');
        $endereco_cidade = trim($_POST['endereco_cidade'] ?? '');
        $endereco_uf = trim($_POST['endereco_uf'] ?? '');
        $endereco_cep = trim($_POST['endereco_cep'] ?? '');
        
        // Campos PIX
        $pix_banco = trim($_POST['pix_banco'] ?? '');
        $pix_titular = trim($_POST['pix_titular'] ?? '');
        $pix_chave = trim($_POST['pix_chave'] ?? '');
        $pix_observacao = trim($_POST['pix_observacao'] ?? '');

        // Validações
        if (empty($nome)) {
            $_SESSION['error'] = 'Nome do CFC é obrigatório.';
            redirect(base_url('configuracoes/cfc'));
        }

        if (strlen($nome) > 255) {
            $_SESSION['error'] = 'Nome do CFC muito longo (máximo 255 caracteres).';
            redirect(base_url('configuracoes/cfc'));
        }

        // Validar CNPJ se fornecido (formato básico)
        if (!empty($cnpj) && strlen($cnpj) > 18) {
            $_SESSION['error'] = 'CNPJ inválido (máximo 18 caracteres).';
            redirect(base_url('configuracoes/cfc'));
        }

        // Validar email se fornecido
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Validar telefone (tamanho máximo)
        if (!empty($telefone) && strlen($telefone) > 20) {
            $_SESSION['error'] = 'Telefone muito longo (máximo 20 caracteres).';
            redirect(base_url('configuracoes/cfc'));
        }

        // Validar UF se fornecido (deve ter 2 caracteres)
        if (!empty($endereco_uf) && strlen($endereco_uf) !== 2) {
            $_SESSION['error'] = 'UF deve ter exatamente 2 caracteres.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Normalizar UF (uppercase)
        if (!empty($endereco_uf)) {
            $endereco_uf = strtoupper($endereco_uf);
        }

        // Preparar dados para atualização
        $data = ['nome' => $nome];
        if (isset($_POST['cnpj'])) {
            $data['cnpj'] = !empty($cnpj) ? $cnpj : null;
        }
        if (isset($_POST['telefone'])) {
            $data['telefone'] = !empty($telefone) ? $telefone : null;
        }
        if (isset($_POST['email'])) {
            $data['email'] = !empty($email) ? $email : null;
        }

        // Campos de endereço estruturado
        if (isset($_POST['endereco_logradouro'])) {
            $data['endereco_logradouro'] = !empty($endereco_logradouro) ? $endereco_logradouro : null;
        }
        if (isset($_POST['endereco_numero'])) {
            $data['endereco_numero'] = !empty($endereco_numero) ? $endereco_numero : null;
        }
        if (isset($_POST['endereco_complemento'])) {
            $data['endereco_complemento'] = !empty($endereco_complemento) ? $endereco_complemento : null;
        }
        if (isset($_POST['endereco_bairro'])) {
            $data['endereco_bairro'] = !empty($endereco_bairro) ? $endereco_bairro : null;
        }
        if (isset($_POST['endereco_cidade'])) {
            $data['endereco_cidade'] = !empty($endereco_cidade) ? $endereco_cidade : null;
        }
        if (isset($_POST['endereco_uf'])) {
            $data['endereco_uf'] = !empty($endereco_uf) ? $endereco_uf : null;
        }
        if (isset($_POST['endereco_cep'])) {
            $data['endereco_cep'] = !empty($endereco_cep) ? $endereco_cep : null;
        }
        
        // Campos PIX
        if (isset($_POST['pix_banco'])) {
            $data['pix_banco'] = !empty($pix_banco) ? $pix_banco : null;
        }
        if (isset($_POST['pix_titular'])) {
            $data['pix_titular'] = !empty($pix_titular) ? $pix_titular : null;
        }
        if (isset($_POST['pix_chave'])) {
            $data['pix_chave'] = !empty($pix_chave) ? $pix_chave : null;
        }
        if (isset($_POST['pix_observacao'])) {
            $data['pix_observacao'] = !empty($pix_observacao) ? $pix_observacao : null;
        }

        // Compatibilidade: atualizar campo endereco (TEXT) apenas se algum campo novo vier preenchido
        $hasStructuredAddress = !empty($endereco_logradouro) || !empty($endereco_numero) || 
                                 !empty($endereco_complemento) || !empty($endereco_bairro) || 
                                 !empty($endereco_cidade) || !empty($endereco_uf) || !empty($endereco_cep);
        
        if ($hasStructuredAddress) {
            // Montar string de endereço completo a partir das partes
            $parts = array_filter([
                $endereco_logradouro,
                $endereco_numero,
                $endereco_complemento,
                $endereco_bairro,
                $endereco_cidade,
                $endereco_uf,
                $endereco_cep
            ]);
            
            if (!empty($parts)) {
                $data['endereco'] = implode(', ', $parts);
            } else {
                $data['endereco'] = null;
            }
        }

        // Atualizar banco
        $dataBefore = $cfc;
        $cfcModel->update($cfc['id'], $data);

        // Auditoria
        $this->auditService->logUpdate('cfcs', $cfc['id'], $dataBefore, array_merge($cfc, $data));

        $_SESSION['success'] = 'Informações do CFC atualizadas com sucesso!';
        redirect(base_url('configuracoes/cfc'));
    }

    /**
     * Servir logo do CFC (protegido)
     */
    /**
     * FASE 7: Servir logo via rota dedicada (robusta e com cache buster)
     */
    public function logo()
    {
        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc || empty($cfc['logo_path'])) {
            http_response_code(404);
            header('Content-Type: text/plain');
            exit('Logo não encontrado (CFC sem logo_path)');
        }

        $filepath = dirname(__DIR__, 2) . '/' . $cfc['logo_path'];

        if (!file_exists($filepath)) {
            http_response_code(404);
            header('Content-Type: text/plain');
            exit('Logo não encontrado (arquivo não existe: ' . $filepath . ')');
        }

        // Determinar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        
        if (!$mimeType) {
            $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp'
            ];
            $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';
        }

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=3600');
        readfile($filepath);
        exit;
    }

    // ============================================
    // MÓDULO: CONTAS PIX MÚLTIPLAS
    // ============================================

    /**
     * Criar nova conta PIX
     */
    public function pixAccountCriar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        $label = trim($_POST['label'] ?? '');
        $bankCode = trim($_POST['bank_code'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $agency = trim($_POST['agency'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $accountType = trim($_POST['account_type'] ?? '');
        $holderName = trim($_POST['holder_name'] ?? '');
        $holderDocument = trim($_POST['holder_document'] ?? '');
        $pixKey = trim($_POST['pix_key'] ?? '');
        $pixKeyType = $_POST['pix_key_type'] ?? null;
        $note = trim($_POST['note'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validações obrigatórias
        if (empty($label)) {
            $_SESSION['error'] = 'Apelido da conta é obrigatório.';
            redirect(base_url('configuracoes/cfc'));
        }

        if (empty($holderName)) {
            $_SESSION['error'] = 'Nome do titular é obrigatório.';
            redirect(base_url('configuracoes/cfc'));
        }

        if (empty($pixKey)) {
            $_SESSION['error'] = 'Chave PIX é obrigatória.';
            redirect(base_url('configuracoes/cfc'));
        }

        $pixAccountModel = new CfcPixAccount();
        
        // Se for padrão, remover padrão das outras
        if ($isDefault) {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE cfc_pix_accounts SET is_default = 0 WHERE cfc_id = ?");
            $stmt->execute([$cfc['id']]);
        }

        $data = [
            'cfc_id' => $cfc['id'],
            'label' => $label,
            'bank_code' => !empty($bankCode) ? $bankCode : null,
            'bank_name' => !empty($bankName) ? $bankName : null,
            'agency' => !empty($agency) ? $agency : null,
            'account_number' => !empty($accountNumber) ? $accountNumber : null,
            'account_type' => !empty($accountType) ? $accountType : null,
            'holder_name' => $holderName,
            'holder_document' => !empty($holderDocument) ? $holderDocument : null,
            'pix_key' => $pixKey,
            'pix_key_type' => $pixKeyType,
            'note' => !empty($note) ? $note : null,
            'is_default' => $isDefault,
            'is_active' => $isActive
        ];

        $id = $pixAccountModel->create($data);
        $this->auditService->logCreate('cfc_pix_accounts', $id, $data);

        $_SESSION['success'] = 'Conta PIX criada com sucesso!';
        redirect(base_url('configuracoes/cfc'));
    }

    /**
     * Atualizar conta PIX
     */
    public function pixAccountAtualizar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        $pixAccountModel = new CfcPixAccount();
        $account = $pixAccountModel->findByIdAndCfc($id, $cfc['id']);

        if (!$account) {
            $_SESSION['error'] = 'Conta PIX não encontrada.';
            redirect(base_url('configuracoes/cfc'));
        }

        $label = trim($_POST['label'] ?? '');
        $bankCode = trim($_POST['bank_code'] ?? '');
        $bankName = trim($_POST['bank_name'] ?? '');
        $agency = trim($_POST['agency'] ?? '');
        $accountNumber = trim($_POST['account_number'] ?? '');
        $accountType = trim($_POST['account_type'] ?? '');
        $holderName = trim($_POST['holder_name'] ?? '');
        $holderDocument = trim($_POST['holder_document'] ?? '');
        $pixKey = trim($_POST['pix_key'] ?? '');
        $pixKeyType = $_POST['pix_key_type'] ?? null;
        $note = trim($_POST['note'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validações obrigatórias
        if (empty($label)) {
            $_SESSION['error'] = 'Apelido da conta é obrigatório.';
            redirect(base_url('configuracoes/cfc'));
        }

        if (empty($holderName)) {
            $_SESSION['error'] = 'Nome do titular é obrigatório.';
            redirect(base_url('configuracoes/cfc'));
        }

        if (empty($pixKey)) {
            $_SESSION['error'] = 'Chave PIX é obrigatória.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Se for padrão, remover padrão das outras
        if ($isDefault && !$account['is_default']) {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE cfc_pix_accounts SET is_default = 0 WHERE cfc_id = ?");
            $stmt->execute([$cfc['id']]);
        }

        $dataBefore = $account;
        $data = [
            'label' => $label,
            'bank_code' => !empty($bankCode) ? $bankCode : null,
            'bank_name' => !empty($bankName) ? $bankName : null,
            'agency' => !empty($agency) ? $agency : null,
            'account_number' => !empty($accountNumber) ? $accountNumber : null,
            'account_type' => !empty($accountType) ? $accountType : null,
            'holder_name' => $holderName,
            'holder_document' => !empty($holderDocument) ? $holderDocument : null,
            'pix_key' => $pixKey,
            'pix_key_type' => $pixKeyType,
            'note' => !empty($note) ? $note : null,
            'is_default' => $isDefault,
            'is_active' => $isActive
        ];

        $pixAccountModel->update($id, $data);
        $this->auditService->logUpdate('cfc_pix_accounts', $id, $dataBefore, array_merge($account, $data));

        $_SESSION['success'] = 'Conta PIX atualizada com sucesso!';
        redirect(base_url('configuracoes/cfc'));
    }

    /**
     * Excluir/desativar conta PIX
     */
    public function pixAccountExcluir($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        $pixAccountModel = new CfcPixAccount();
        $account = $pixAccountModel->findByIdAndCfc($id, $cfc['id']);

        if (!$account) {
            $_SESSION['error'] = 'Conta PIX não encontrada.';
            redirect(base_url('configuracoes/cfc'));
        }

        // Verificar se há matrículas usando esta conta
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM enrollments WHERE pix_account_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result && $result['cnt'] > 0) {
            // Se houver matrículas usando, apenas desativar
            $dataBefore = $account;
            $pixAccountModel->update($id, ['is_active' => 0]);
            $this->auditService->logUpdate('cfc_pix_accounts', $id, $dataBefore, array_merge($account, ['is_active' => 0]));
            $_SESSION['success'] = 'Conta PIX desativada (há matrículas usando esta conta).';
        } else {
            // Se não houver uso, pode excluir
            $dataBefore = $account;
            $pixAccountModel->delete($id);
            $this->auditService->logDelete('cfc_pix_accounts', $id, $dataBefore);
            $_SESSION['success'] = 'Conta PIX excluída com sucesso!';
        }

        redirect(base_url('configuracoes/cfc'));
    }

    /**
     * Definir conta como padrão
     */
    public function pixAccountDefinirPadrao($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('configuracoes/cfc'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('configuracoes/cfc'));
        }

        $cfcModel = new Cfc();
        $cfc = $cfcModel->getCurrent();

        if (!$cfc) {
            $_SESSION['error'] = 'CFC não encontrado.';
            redirect(base_url('configuracoes/cfc'));
        }

        $pixAccountModel = new CfcPixAccount();
        $account = $pixAccountModel->findByIdAndCfc($id, $cfc['id']);

        if (!$account) {
            $_SESSION['error'] = 'Conta PIX não encontrada.';
            redirect(base_url('configuracoes/cfc'));
        }

        $pixAccountModel->setAsDefault($id, $cfc['id']);
        $_SESSION['success'] = 'Conta PIX definida como padrão!';
        redirect(base_url('configuracoes/cfc'));
    }
}
