<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\Instructor;
use App\Models\AccountActivationToken;
use App\Services\PermissionService;
use App\Services\AuditService;
use App\Services\EmailService;
use App\Services\UserCreationService;
use App\Config\Constants;
use App\Config\Database;

class UsuariosController extends Controller
{
    private $cfcId;
    private $auditService;
    private $emailService;

    public function __construct()
    {
        $this->cfcId = $_SESSION['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $this->auditService = new AuditService();
        $this->emailService = new EmailService();
        
        // Apenas ADMIN pode gerenciar usuários
        if (!PermissionService::check('usuarios', 'view') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para acessar este módulo.';
            redirect(base_url('dashboard'));
        }
    }

    /**
     * Lista todos os usuários e pendências
     */
    public function index()
    {
        $userModel = new User();
        $users = $userModel->findAllWithLinks($this->cfcId);
        
        // Processar roles para exibição
        foreach ($users as &$user) {
            $roles = explode(',', $user['roles'] ?? '');
            $user['roles_array'] = array_filter($roles);
        }
        
        // Buscar pendências: alunos e instrutores sem acesso
        $db = Database::getInstance()->getConnection();
        
        // Alunos sem usuário (com e-mail para poder criar acesso)
        $stmt = $db->prepare("
            SELECT id, name, full_name, cpf, email, status
            FROM students 
            WHERE cfc_id = ? 
            AND (user_id IS NULL OR user_id = 0)
            AND email IS NOT NULL 
            AND email != ''
            ORDER BY COALESCE(full_name, name) ASC
        ");
        $stmt->execute([$this->cfcId]);
        $studentsWithoutAccess = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Instrutores sem usuário (com e-mail)
        $stmt = $db->prepare("
            SELECT id, name, cpf, email, is_active
            FROM instructors 
            WHERE cfc_id = ? 
            AND (user_id IS NULL OR user_id = 0)
            AND email IS NOT NULL 
            AND email != ''
            ORDER BY name ASC
        ");
        $stmt->execute([$this->cfcId]);
        $instructorsWithoutAccess = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $data = [
            'pageTitle' => 'Gerenciamento de Usuários',
            'users' => $users,
            'studentsWithoutAccess' => $studentsWithoutAccess,
            'instructorsWithoutAccess' => $instructorsWithoutAccess
        ];
        
        $this->view('usuarios/index', $data);
    }

    /**
     * Formulário para criar/vincular acesso
     */
    public function novo()
    {
        if (!PermissionService::check('usuarios', 'create') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para criar usuários.';
            redirect(base_url('usuarios'));
        }

        $studentModel = new Student();
        $instructorModel = new Instructor();
        
        // Buscar alunos e instrutores sem usuário vinculado
        // Inclui alunos sem user_id OU com user_id que não existe na tabela usuarios
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT s.id, s.name, s.full_name, s.cpf, s.email, s.user_id
            FROM students s
            LEFT JOIN usuarios u ON u.id = s.user_id
            WHERE s.cfc_id = ? 
            AND (s.user_id IS NULL OR s.user_id = 0 OR u.id IS NULL)
            AND s.email IS NOT NULL 
            AND s.email != ''
            ORDER BY COALESCE(s.full_name, s.name) ASC
        ");
        $stmt->execute([$this->cfcId]);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Log para diagnóstico
        error_log("[USUARIOS_NOVO] Alunos encontrados sem acesso: " . count($students));
        
        // Buscar instrutores sem usuário vinculado
        // Inclui instrutores sem user_id OU com user_id que não existe na tabela usuarios
        $stmt = $db->prepare("
            SELECT i.id, i.name, i.cpf, i.email, i.user_id
            FROM instructors i
            LEFT JOIN usuarios u ON u.id = i.user_id
            WHERE i.cfc_id = ? 
            AND (i.user_id IS NULL OR i.user_id = 0 OR u.id IS NULL)
            AND i.email IS NOT NULL 
            AND i.email != ''
            ORDER BY i.name ASC
        ");
        $stmt->execute([$this->cfcId]);
        $instructors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Log para diagnóstico
        error_log("[USUARIOS_NOVO] Instrutores encontrados sem acesso: " . count($instructors));
        
        $data = [
            'pageTitle' => 'Criar Acesso',
            'students' => $students,
            'instructors' => $instructors
        ];
        
        $this->view('usuarios/form', $data);
    }

    /**
     * Cria novo acesso/vínculo
     */
    public function criar()
    {
        if (!PermissionService::check('usuarios', 'create') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para criar usuários.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('usuarios/novo'));
        }

        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $linkType = $_POST['link_type'] ?? 'none'; // 'student', 'instructor', 'none'
        $linkId = !empty($_POST['link_id']) ? (int)$_POST['link_id'] : null;
        $sendEmail = isset($_POST['send_email']);

        // Validações
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail inválido.';
            redirect(base_url('usuarios/novo'));
        }

        if (empty($role) || !in_array($role, ['ADMIN', 'SECRETARIA', 'INSTRUTOR', 'ALUNO'])) {
            $_SESSION['error'] = 'Perfil inválido.';
            redirect(base_url('usuarios/novo'));
        }

        // Validar vínculo
        $userModel = new User();
        if ($linkType === 'student' && $linkId) {
            if ($userModel->hasStudentUser($linkId)) {
                $_SESSION['error'] = 'Este aluno já possui um acesso vinculado.';
                redirect(base_url('usuarios/novo'));
            }
        } elseif ($linkType === 'instructor' && $linkId) {
            if ($userModel->hasInstructorUser($linkId)) {
                $_SESSION['error'] = 'Este instrutor já possui um acesso vinculado.';
                redirect(base_url('usuarios/novo'));
            }
        }

        // Verificar se email já existe
        $existing = $userModel->findByEmail($email);
        if ($existing) {
            $_SESSION['error'] = 'Este e-mail já está em uso.';
            redirect(base_url('usuarios/novo'));
        }

        // Gerar senha temporária segura
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $tempPassword = substr(str_shuffle(str_repeat($chars, ceil(12 / strlen($chars)))), 0, 12);
        $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

        // Buscar nome do vínculo
        $nome = '';
        if ($linkType === 'student' && $linkId) {
            $studentModel = new Student();
            $student = $studentModel->find($linkId);
            $nome = $student['full_name'] ?? $student['name'] ?? 'Aluno';
        } elseif ($linkType === 'instructor' && $linkId) {
            $instructorModel = new Instructor();
            $instructor = $instructorModel->find($linkId);
            $nome = $instructor['name'] ?? 'Instrutor';
        } else {
            $nome = trim($_POST['nome'] ?? '');
            if (empty($nome)) {
                $_SESSION['error'] = 'Nome é obrigatório para usuários administrativos.';
                redirect(base_url('usuarios/novo'));
            }
        }

        $db = Database::getInstance()->getConnection();
        
        try {
            $db->beginTransaction();

            // Criar usuário (com must_change_password = 1 para senhas temporárias)
            $mustChangePassword = ($linkType !== 'none') ? 1 : 0; // Senhas vinculadas devem ser trocadas
            $stmt = $db->prepare("
                INSERT INTO usuarios (cfc_id, nome, email, password, status, must_change_password) 
                VALUES (?, ?, ?, ?, 'ativo', ?)
            ");
            $stmt->execute([$this->cfcId, $nome, $email, $hashedPassword, $mustChangePassword]);
            $userId = $db->lastInsertId();

            // Vincular com aluno ou instrutor
            if ($linkType === 'student' && $linkId) {
                $stmt = $db->prepare("UPDATE students SET user_id = ? WHERE id = ?");
                $stmt->execute([$userId, $linkId]);
            } elseif ($linkType === 'instructor' && $linkId) {
                $stmt = $db->prepare("UPDATE instructors SET user_id = ? WHERE id = ?");
                $stmt->execute([$userId, $linkId]);
            }

            // Associar role
            $stmt = $db->prepare("INSERT INTO usuario_roles (usuario_id, role) VALUES (?, ?)");
            $stmt->execute([$userId, $role]);

            $db->commit();

            // Auditoria
            $this->auditService->logCreate('usuarios', $userId, [
                'email' => $email,
                'role' => $role,
                'link_type' => $linkType,
                'link_id' => $linkId
            ]);

            // Enviar e-mail se solicitado
            if ($sendEmail) {
                try {
                    $loginUrl = base_url('/login');
                    $this->emailService->sendAccessCreated($email, $tempPassword, $loginUrl);
                } catch (\Exception $e) {
                    // Log erro mas não bloqueia criação
                    error_log("Erro ao enviar e-mail: " . $e->getMessage());
                }
            }

            $_SESSION['success'] = 'Acesso criado com sucesso!' . ($sendEmail ? ' E-mail enviado.' : '');
            redirect(base_url('usuarios'));
            
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Erro ao criar acesso: ' . $e->getMessage();
            redirect(base_url('usuarios/novo'));
        }
    }

    /**
     * Formulário para editar usuário
     */
    public function editar($id)
    {
        error_log("[USUARIOS_EDITAR] Método chamado para ID: {$id}");
        error_log("[USUARIOS_EDITAR] just_generated_temp_password: " . (isset($_SESSION['just_generated_temp_password']) ? 'SIM' : 'NÃO'));
        
        // Se acabamos de gerar senha temporária, não verificar permissão novamente
        // (já foi verificado no método gerarSenhaTemporaria)
        $justGeneratedPassword = !empty($_SESSION['just_generated_temp_password']);
        if ($justGeneratedPassword) {
            unset($_SESSION['just_generated_temp_password']);
            error_log("[USUARIOS_EDITAR] Flag just_generated_temp_password encontrada. Pulando verificação de permissão.");
        } else {
            if (!PermissionService::check('usuarios', 'update') && $_SESSION['current_role'] !== 'ADMIN') {
                error_log("[USUARIOS_EDITAR] Erro: Sem permissão para editar. Role: " . ($_SESSION['current_role'] ?? 'N/A'));
                $_SESSION['error'] = 'Você não tem permissão para editar usuários.';
                redirect(base_url('usuarios'));
            }
        }

        $userModel = new User();
        $user = $userModel->findWithLinks($id);

        if (!$user || $user['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('usuarios'));
        }

        // Buscar roles do usuário
        $roles = User::getUserRoles($id);
        $user['roles'] = $roles;
        
        // Garantir que há pelo menos um role para o formulário
        if (empty($roles) || !is_array($roles)) {
            $user['roles'] = [];
        }
        
        // Log para debug
        error_log("[USUARIOS_EDITAR] Roles encontrados para usuário {$id}: " . print_r($roles, true));
        error_log("[USUARIOS_EDITAR] Current role para formulário: " . ($user['roles'][0]['role'] ?? 'NENHUM'));

        // Verificar status de acesso
        $db = Database::getInstance()->getConnection();
        
        // Verificar se tem senha definida (senha não pode ser vazia)
        $hasPassword = !empty($user['password']);
        
        // Verificar se tem token de ativação ativo
        $tokenModel = new AccountActivationToken();
        $activeToken = $tokenModel->findActiveToken($id);
        $hasActiveToken = !empty($activeToken);
        
        // Buscar último login (se houver campo na tabela)
        $lastLogin = null;
        // TODO: Adicionar campo last_login em usuarios se necessário

        // Verificar se há senha temporária gerada na sessão
        $tempPasswordGenerated = null;
        if (!empty($_SESSION['temp_password_generated'])) {
            $tempPasswordGenerated = $_SESSION['temp_password_generated'];
            error_log("[USUARIOS_EDITAR] Senha temporária encontrada na sessão: " . print_r($tempPasswordGenerated, true));
            error_log("[USUARIOS_EDITAR] Comparando user_id: sessão={$tempPasswordGenerated['user_id']}, atual={$id}");
            
            // Verificar se a senha é para este usuário
            if ((int)$tempPasswordGenerated['user_id'] === (int)$id) {
                error_log("[USUARIOS_EDITAR] Senha temporária corresponde ao usuário atual. Mantendo na sessão para exibição.");
            } else {
                error_log("[USUARIOS_EDITAR] Senha temporária não corresponde ao usuário atual. Limpando.");
                unset($_SESSION['temp_password_generated']);
                $tempPasswordGenerated = null;
            }
        }

        $data = [
            'pageTitle' => 'Editar Usuário',
            'user' => $user,
            'hasPassword' => $hasPassword,
            'hasActiveToken' => $hasActiveToken,
            'activeToken' => $activeToken,
            'tempPasswordGenerated' => $tempPasswordGenerated,
            'activationLinkGenerated' => $_SESSION['activation_link_generated'] ?? null
        ];

        // Limpar sessões após passar para a view (será limpo após renderizar)
        // Não limpar aqui para garantir que a view tenha acesso aos dados

        error_log("[USUARIOS_EDITAR] Dados passados para view - tempPasswordGenerated: " . ($tempPasswordGenerated ? 'SIM' : 'NÃO'));
        
        $this->view('usuarios/form', $data);
        
        // Limpar sessões após renderizar a view
        if (!empty($_SESSION['temp_password_generated']) && (int)$_SESSION['temp_password_generated']['user_id'] === (int)$id) {
            unset($_SESSION['temp_password_generated']);
            error_log("[USUARIOS_EDITAR] Sessão temp_password_generated limpa após renderização.");
        }
        if (!empty($_SESSION['activation_link_generated']) && (int)$_SESSION['activation_link_generated']['user_id'] === (int)$id) {
            unset($_SESSION['activation_link_generated']);
        }
    }

    /**
     * Atualiza usuário
     */
    public function atualizar($id)
    {
        // Log para debug
        error_log("[USUARIOS_ATUALIZAR] Método chamado para ID: {$id}");
        error_log("[USUARIOS_ATUALIZAR] POST data: " . print_r($_POST, true));
        
        if (!PermissionService::check('usuarios', 'update') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para editar usuários.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        $userModel = new User();
        $user = $userModel->find($id);

        if (!$user || $user['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('usuarios'));
        }

        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? 'ativo';
        
        error_log("[USUARIOS_ATUALIZAR] Dados extraídos - Email: {$email}, Role: {$role}, Status: {$status}");

        // Validações
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail inválido.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        if (empty($role) || !in_array($role, ['ADMIN', 'SECRETARIA', 'INSTRUTOR', 'ALUNO'])) {
            $_SESSION['error'] = 'Perfil inválido.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        // Verificar se email já existe em outro usuário
        $existing = $userModel->findByEmail($email);
        if ($existing && $existing['id'] != $id) {
            $_SESSION['error'] = 'Este e-mail já está em uso por outro usuário.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        // Não permitir alterar vínculo (aluno/instrutor)
        // Apenas email, role e status podem ser alterados

        $db = Database::getInstance()->getConnection();
        
        // Verificar se já está em transação
        $inTransaction = $db->inTransaction();
        error_log("[USUARIOS_ATUALIZAR] Já está em transação: " . ($inTransaction ? 'sim' : 'não'));
        
        try {
            if (!$inTransaction) {
                $db->beginTransaction();
                error_log("[USUARIOS_ATUALIZAR] Transação iniciada");
            }

            $dataBefore = $user;

            // Atualizar usuário
            $stmt = $db->prepare("UPDATE usuarios SET email = ?, status = ? WHERE id = ?");
            $result1 = $stmt->execute([$email, $status, $id]);
            $rowsAffected1 = $stmt->rowCount();
            error_log("[USUARIOS_ATUALIZAR] UPDATE usuarios executado. Rows affected: {$rowsAffected1}, Success: " . ($result1 ? 'true' : 'false'));

            // Atualizar role (remover antigas e adicionar nova)
            $stmt = $db->prepare("DELETE FROM usuario_roles WHERE usuario_id = ?");
            $result2 = $stmt->execute([$id]);
            $rowsAffected2 = $stmt->rowCount();
            error_log("[USUARIOS_ATUALIZAR] DELETE usuario_roles executado. Rows affected: {$rowsAffected2}, Success: " . ($result2 ? 'true' : 'false'));
            
            $stmt = $db->prepare("INSERT INTO usuario_roles (usuario_id, role) VALUES (?, ?)");
            $result3 = $stmt->execute([$id, $role]);
            $rowsAffected3 = $stmt->rowCount();
            error_log("[USUARIOS_ATUALIZAR] INSERT usuario_roles executado. Rows affected: {$rowsAffected3}, Success: " . ($result3 ? 'true' : 'false'));

            if (!$inTransaction) {
                $commitResult = $db->commit();
                error_log("[USUARIOS_ATUALIZAR] Commit executado. Success: " . ($commitResult ? 'true' : 'false'));
            } else {
                error_log("[USUARIOS_ATUALIZAR] Não foi necessário fazer commit (já estava em transação externa)");
            }
            
            // Verificar se realmente foi salvo - usar query direta para evitar cache
            $stmt = $db->prepare("SELECT email, status FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $userAfter = $stmt->fetch();
            error_log("[USUARIOS_ATUALIZAR] Dados após commit (query direta) - Email: " . ($userAfter['email'] ?? 'N/A') . ", Status: " . ($userAfter['status'] ?? 'N/A'));
            
            // Verificar role também
            $stmt = $db->prepare("SELECT role FROM usuario_roles WHERE usuario_id = ?");
            $stmt->execute([$id]);
            $roleAfter = $stmt->fetch();
            error_log("[USUARIOS_ATUALIZAR] Role após commit: " . ($roleAfter['role'] ?? 'N/A'));

            // Auditoria
            $dataAfter = array_merge($user, ['email' => $email, 'status' => $status, 'role' => $role]);
            $this->auditService->logUpdate('usuarios', $id, $dataBefore, $dataAfter);

            // Etapa B: se o usuário for ADMIN e tiver vínculo com instrutor, garantir também role INSTRUTOR
            if ($role === 'ADMIN') {
                $this->syncAdminInstructorRoles($id, $email);
            }

            $_SESSION['success'] = 'Usuário atualizado com sucesso!';
            redirect(base_url('usuarios'));
            
        } catch (\Exception $e) {
            error_log("[USUARIOS_ATUALIZAR] ERRO capturado: " . $e->getMessage());
            error_log("[USUARIOS_ATUALIZAR] Stack trace: " . $e->getTraceAsString());
            
            if ($db->inTransaction()) {
                $db->rollBack();
                error_log("[USUARIOS_ATUALIZAR] Rollback executado");
            }
            
            $_SESSION['error'] = 'Erro ao atualizar usuário: ' . $e->getMessage();
            redirect(base_url("usuarios/{$id}/editar"));
        }
    }

    /**
     * Se o usuário é ADMIN e possui vínculo com instrutor (por user_id ou por e-mail),
     * garantir que ele também tenha a role INSTRUTOR em usuario_roles.
     */
    private function syncAdminInstructorRoles($userId, $userEmail)
    {
        $db = Database::getInstance()->getConnection();

        // Preferir vínculo explícito por user_id
        $stmt = $db->prepare("SELECT id, email, user_id FROM instructors WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $instructor = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$instructor) {
            // Fallback seguro: tentar por e-mail igual
            $stmt = $db->prepare("SELECT id, email, user_id FROM instructors WHERE email = ? LIMIT 1");
            $stmt->execute([$userEmail]);
            $instructor = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($instructor) {
                // Apenas vincular automaticamente se não houver user_id ou se já apontar para este usuário
                if (empty($instructor['user_id']) || (int)$instructor['user_id'] === (int)$userId) {
                    $stmtUpdate = $db->prepare("UPDATE instructors SET user_id = ? WHERE id = ?");
                    $stmtUpdate->execute([$userId, $instructor['id']]);
                } else {
                    // E-mail diferente / vínculo ambíguo – não tentar adivinhar
                    error_log("[UsuariosController] syncAdminInstructorRoles: instrutor {$instructor['id']} já vinculado a outro usuário ({$instructor['user_id']}). Nenhuma alteração automática.");
                    return;
                }
            }
        }

        // Se após as verificações temos um instrutor vinculado a este usuário, garantir role INSTRUTOR
        if ($instructor) {
            $stmt = $db->prepare("SELECT 1 FROM usuario_roles WHERE usuario_id = ? AND role = 'INSTRUTOR' LIMIT 1");
            $stmt->execute([$userId]);
            $exists = $stmt->fetchColumn();

            if (!$exists) {
                $stmtInsert = $db->prepare("INSERT INTO usuario_roles (usuario_id, role) VALUES (?, 'INSTRUTOR')");
                $stmtInsert->execute([$userId]);
                error_log("[UsuariosController] syncAdminInstructorRoles: role INSTRUTOR adicionada ao usuário {$userId}");
            }
        }
    }

    /**
     * Cria acesso rápido para aluno (da lista de pendências)
     */
    public function criarAcessoAluno()
    {
        if (!PermissionService::check('usuarios', 'create') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para criar acessos.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('usuarios'));
        }

        $studentId = (int)($_POST['student_id'] ?? 0);

        if (!$studentId) {
            $_SESSION['error'] = 'Aluno não especificado.';
            redirect(base_url('usuarios'));
        }

        $studentModel = new Student();
        $student = $studentModel->find($studentId);

        if (!$student || $student['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Aluno não encontrado.';
            redirect(base_url('usuarios'));
        }

        // Verificar se aluno já tem usuário válido (que existe na tabela usuarios)
        if (!empty($student['user_id'])) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$student['user_id']]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                $_SESSION['error'] = 'Este aluno já possui acesso vinculado.';
                redirect(base_url('usuarios'));
            } else {
                // user_id existe mas usuário não existe - limpar referência inválida
                error_log("[USUARIOS] Aluno ID {$studentId} tem user_id inválido ({$student['user_id']}). Limpando referência.");
                $studentModel->update($studentId, ['user_id' => null]);
                $student['user_id'] = null; // Atualizar para continuar
            }
        }

        $email = trim($student['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Aluno não possui e-mail válido. Atualize o cadastro do aluno primeiro.';
            redirect(base_url('usuarios'));
        }

        try {
            $userService = new UserCreationService();
            $userData = $userService->createForStudent($studentId, $email, $student['full_name'] ?? $student['name']);

            // Tentar enviar e-mail
            try {
                $emailService = new EmailService();
                $loginUrl = base_url('/login');
                $emailService->sendAccessCreated($email, $userData['temp_password'], $loginUrl);
            } catch (\Exception $e) {
                error_log("Erro ao enviar e-mail: " . $e->getMessage());
            }

            $this->auditService->logCreate('usuarios', $userData['user_id'], [
                'type' => 'student_access',
                'student_id' => $studentId
            ]);

            $_SESSION['success'] = 'Acesso criado com sucesso para o aluno!';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao criar acesso: ' . $e->getMessage();
        }

        redirect(base_url('usuarios'));
    }

    /**
     * Cria acesso rápido para instrutor (da lista de pendências)
     */
    public function criarAcessoInstrutor()
    {
        if (!PermissionService::check('usuarios', 'create') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para criar acessos.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('usuarios'));
        }

        $instructorId = (int)($_POST['instructor_id'] ?? 0);

        if (!$instructorId) {
            $_SESSION['error'] = 'Instrutor não especificado.';
            redirect(base_url('usuarios'));
        }

        $instructorModel = new Instructor();
        $instructor = $instructorModel->find($instructorId);

        if (!$instructor || $instructor['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Instrutor não encontrado.';
            redirect(base_url('usuarios'));
        }

        if (!empty($instructor['user_id'])) {
            $_SESSION['error'] = 'Este instrutor já possui acesso vinculado.';
            redirect(base_url('usuarios'));
        }

        $email = trim($instructor['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Instrutor não possui e-mail válido. Atualize o cadastro do instrutor primeiro.';
            redirect(base_url('usuarios'));
        }

        try {
            $userService = new UserCreationService();
            $userData = $userService->createForInstructor($instructorId, $email, $instructor['name']);

            // Tentar enviar e-mail
            try {
                $emailService = new EmailService();
                $loginUrl = base_url('/login');
                $emailService->sendAccessCreated($email, $userData['temp_password'], $loginUrl);
            } catch (\Exception $e) {
                error_log("Erro ao enviar e-mail: " . $e->getMessage());
            }

            $this->auditService->logCreate('usuarios', $userData['user_id'], [
                'type' => 'instructor_access',
                'instructor_id' => $instructorId
            ]);

            $_SESSION['success'] = 'Acesso criado com sucesso para o instrutor!';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao criar acesso: ' . $e->getMessage();
        }

        redirect(base_url('usuarios'));
    }

    /**
     * Gera senha temporária para usuário
     */
    public function gerarSenhaTemporaria($id)
    {
        error_log("[GERAR_SENHA_TEMP] Iniciando geração de senha temporária para usuário ID: {$id}");
        
        if (!PermissionService::check('usuarios', 'update') && $_SESSION['current_role'] !== 'ADMIN') {
            error_log("[GERAR_SENHA_TEMP] Erro: Sem permissão. Role atual: " . ($_SESSION['current_role'] ?? 'N/A'));
            $_SESSION['error'] = 'Você não tem permissão para esta ação.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            error_log("[GERAR_SENHA_TEMP] Erro: Token CSRF inválido");
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        $userModel = new User();
        $user = $userModel->find($id);

        if (!$user || $user['cfc_id'] != $this->cfcId) {
            error_log("[GERAR_SENHA_TEMP] Erro: Usuário não encontrado. ID: {$id}, CFC: {$this->cfcId}");
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('usuarios'));
        }

        // Gerar senha temporária segura
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
        $tempPassword = substr(str_shuffle(str_repeat($chars, ceil(12 / strlen($chars)))), 0, 12);
        $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

        // Atualizar senha e marcar como obrigatória troca
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE usuarios 
            SET password = ?, must_change_password = 1 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $id]);

        // Auditoria
        $this->auditService->logUpdate('usuarios', $id, null, [
            'action' => 'generate_temp_password',
            'generated_by' => $_SESSION['user_id']
        ]);

        // Retornar senha temporária (exibir apenas uma vez)
        $_SESSION['temp_password_generated'] = [
            'user_id' => (int)$id,
            'user_email' => $user['email'],
            'temp_password' => $tempPassword
        ];
        
        // Flag para indicar que acabamos de gerar senha (evita redirecionamento duplo)
        $_SESSION['just_generated_temp_password'] = true;

        error_log("[GERAR_SENHA_TEMP] Senha temporária gerada e salva na sessão. User ID: {$id}, Email: {$user['email']}");
        error_log("[GERAR_SENHA_TEMP] Sessão temp_password_generated: " . print_r($_SESSION['temp_password_generated'], true));
        error_log("[GERAR_SENHA_TEMP] Redirecionando para: " . base_url("usuarios/{$id}/editar"));

        $_SESSION['success'] = 'Senha temporária gerada com sucesso!';
        redirect(base_url("usuarios/{$id}/editar"));
    }

    /**
     * Gera link de ativação para usuário
     */
    public function gerarLinkAtivacao($id)
    {
        if (!PermissionService::check('usuarios', 'update') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para esta ação.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        $userModel = new User();
        $user = $userModel->find($id);

        if (!$user || $user['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('usuarios'));
        }

        // Gerar token único
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        
        // Expiração: 24 horas
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Salvar token hash no banco
        $tokenModel = new AccountActivationToken();
        $tokenModel->create($id, $tokenHash, $expiresAt, $_SESSION['user_id']);

        // Auditoria
        $this->auditService->logUpdate('usuarios', $id, null, [
            'action' => 'generate_activation_link',
            'generated_by' => $_SESSION['user_id']
        ]);

        // URL completa de ativação
        $activationUrl = base_url("ativar-conta?token={$token}");

        // Retornar link (exibir apenas uma vez)
        // IMPORTANTE: Salvar token puro na sessão para poder usar ao enviar por e-mail
        $_SESSION['activation_link_generated'] = [
            'user_id' => $id,
            'user_email' => $user['email'],
            'activation_url' => $activationUrl,
            'token' => $token, // Token puro para envio por e-mail
            'expires_at' => $expiresAt
        ];

        $_SESSION['success'] = 'Link de ativação gerado com sucesso!';
        redirect(base_url("usuarios/{$id}/editar"));
    }

    /**
     * Envia link de ativação por e-mail
     */
    public function enviarLinkEmail($id)
    {
        if (!PermissionService::check('usuarios', 'update') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para esta ação.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url("usuarios/{$id}/editar"));
        }

        $userModel = new User();
        $user = $userModel->findWithLinks($id);

        if (!$user || $user['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('usuarios'));
        }

        // Garantir token ativo: reutiliza sessão ou gera novo (invalida anteriores)
        $tokenModel = new AccountActivationToken();
        $activeToken = $tokenModel->findActiveToken($id);
        if (!$activeToken) {
            $this->ensureActivationTokenForUser((int) $id, $user);
            $activeToken = $tokenModel->findActiveToken($id);
        }

        // Tentar enviar e-mail (não bloqueia se falhar)
        // Verificar se há token puro na sessão (gerado recentemente)
        $tokenFromSession = null;
        if (!empty($_SESSION['activation_link_generated']) && 
            $_SESSION['activation_link_generated']['user_id'] == $id &&
            !empty($_SESSION['activation_link_generated']['token'])) {
            $tokenFromSession = $_SESSION['activation_link_generated']['token'];
        }

        // Se não houver token na sessão, gerar novo
        if (!$tokenFromSession) {
            $tokenFromSession = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $tokenFromSession);
            
            // Atualizar token no banco
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE account_activation_tokens SET token_hash = ? WHERE id = ?");
            $stmt->execute([$tokenHash, $activeToken['id']]);
        }

        $activationUrl = base_url("ativar-conta?token={$tokenFromSession}");

        try {
            // Verificar se SMTP está configurado
            $smtpSettings = $this->emailService->getSmtpSettings();
            
            if (!$smtpSettings) {
                throw new \Exception('SMTP não configurado');
            }

            // Enviar e-mail
            $this->emailService->sendActivationLink($user['email'], $user['nome'], $activationUrl);

            // Auditoria
            $this->auditService->logUpdate('usuarios', $id, null, [
                'action' => 'send_activation_email',
                'sent_by' => $_SESSION['user_id'],
                'status' => 'success'
            ]);

            $_SESSION['success'] = 'Link de ativação enviado por e-mail com sucesso!';
        } catch (\Exception $e) {
            // Log erro mas não bloqueia
            error_log("Erro ao enviar e-mail de ativação: " . $e->getMessage());
            
            // Auditoria
            $this->auditService->logUpdate('usuarios', $id, null, [
                'action' => 'send_activation_email',
                'sent_by' => $_SESSION['user_id'],
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

            // Mostrar link copiável se SMTP não configurado
            $_SESSION['activation_link_generated'] = [
                'user_id' => $id,
                'user_email' => $user['email'],
                'activation_url' => $activationUrl,
                'expires_at' => $activeToken['expires_at']
            ];

            if (strpos($e->getMessage(), 'SMTP não configurado') !== false) {
                $_SESSION['warning'] = 'SMTP não configurado. Use o link copiável abaixo.';
            } else {
                $_SESSION['warning'] = 'Não foi possível enviar o e-mail automaticamente. Use o link copiável abaixo.';
            }
        }

        redirect(base_url("usuarios/{$id}/editar"));
    }

    /**
     * POST /usuarios/{id}/access-link
     * Obtém ou gera link de ativação (JSON). Usado pelos CTAs "Enviar no WhatsApp" e "Copiar link".
     * Reutiliza token da sessão se válido; senão cria novo (invalida anteriores). Nunca loga token puro.
     */
    public function accessLink($id)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!PermissionService::check('usuarios', 'update') && $_SESSION['current_role'] !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta ação'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $csrf = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
        if (!csrf_verify($csrf)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Token CSRF inválido'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $userModel = new User();
        $user = $userModel->findWithLinks($id);
        if (!$user || $user['cfc_id'] != $this->cfcId) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Usuário não encontrado'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->ensureActivationTokenForUser((int) $id, $user);
        if (!$data) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Não foi possível obter ou gerar o link'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Telefone: usuário (usuarios.telefone) ou, se for aluno, do cadastro do aluno (students.phone_primary/phone)
        $phoneRaw = $user['telefone'] ?? null;
        if (($phoneRaw === null || trim($phoneRaw) === '') && !empty($user['student_id'])) {
            $studentModel = new Student();
            $student = $studentModel->find($user['student_id']);
            if ($student) {
                $phoneRaw = !empty($student['phone_primary']) ? $student['phone_primary'] : ($student['phone'] ?? null);
            }
        }
        list($phoneWa, $phoneValid) = $this->normalizePhoneForWa($phoneRaw);
        $message = 'Olá! Segue seu link para ativar/recuperar seu acesso: ' . $data['url'];

        echo json_encode([
            'ok' => true,
            'url' => $data['url'],
            'expires_at' => $data['expires_at'],
            'phone_wa' => $phoneValid ? $phoneWa : null,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Garante que o usuário tem link de ativação: reutiliza da sessão se válido, senão cria novo (invalida anteriores).
     * Nunca loga token puro. Retorna ['url' => string, 'expires_at' => string] ou null.
     */
    private function ensureActivationTokenForUser($id, array $user)
    {
        $now = date('Y-m-d H:i:s');
        if (!empty($_SESSION['activation_link_generated'])
            && (int) ($_SESSION['activation_link_generated']['user_id'] ?? 0) === (int) $id
            && !empty($_SESSION['activation_link_generated']['activation_url'])
            && !empty($_SESSION['activation_link_generated']['expires_at'])
            && $_SESSION['activation_link_generated']['expires_at'] > $now) {
            return [
                'url' => $_SESSION['activation_link_generated']['activation_url'],
                'expires_at' => $_SESSION['activation_link_generated']['expires_at'],
            ];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $tokenModel = new AccountActivationToken();
        $tokenModel->create($id, $tokenHash, $expiresAt, $_SESSION['user_id'] ?? null);

        $this->auditService->logUpdate('usuarios', $id, null, [
            'action' => 'generate_activation_link',
            'generated_by' => $_SESSION['user_id'] ?? null,
        ]);

        $activationUrl = base_url("ativar-conta?token={$token}");
        $_SESSION['activation_link_generated'] = [
            'user_id' => $id,
            'user_email' => $user['email'],
            'activation_url' => $activationUrl,
            'token' => $token,
            'expires_at' => $expiresAt,
        ];

        return ['url' => $activationUrl, 'expires_at' => $expiresAt];
    }

    /**
     * Normaliza telefone para wa.me: só dígitos, DDI 55. Retorna [numeroParaWa, valido].
     * Valido = 12 ou 13 dígitos (55 + DDD + número).
     */
    private function normalizePhoneForWa($phone)
    {
        if ($phone === null || $phone === '') {
            return [null, false];
        }
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return [null, false];
        }
        if (strlen($digits) === 11 && substr($digits, 0, 2) !== '55') {
            $digits = '55' . $digits;
        } elseif (strlen($digits) > 0 && substr($digits, 0, 2) !== '55') {
            $digits = '55' . $digits;
        }
        $len = strlen($digits);
        $valid = ($len >= 12 && $len <= 13);
        return [$digits, $valid];
    }

    /**
     * Excluir usuário e todos os dados relacionados
     */
    public function excluir($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url('usuarios'));
        }

        if (!PermissionService::check('usuarios', 'delete') && $_SESSION['current_role'] !== 'ADMIN') {
            $_SESSION['error'] = 'Você não tem permissão para excluir usuários.';
            redirect(base_url('usuarios'));
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('usuarios'));
        }

        $userModel = new User();
        $user = $userModel->find($id);

        if (!$user || $user['cfc_id'] != $this->cfcId) {
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('usuarios'));
        }

        // Não permitir excluir o próprio usuário logado
        if ($user['id'] == ($_SESSION['user_id'] ?? 0)) {
            $_SESSION['error'] = 'Você não pode excluir seu próprio usuário.';
            redirect(base_url('usuarios'));
        }

        // Salvar dados para auditoria
        $dataBefore = $user;

        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Remover vínculo com aluno (se houver)
            if (!empty($user['student_id'])) {
                $stmt = $db->prepare("UPDATE students SET user_id = NULL WHERE id = ?");
                $stmt->execute([$user['student_id']]);
            }

            // 2. Remover vínculo com instrutor (se houver)
            if (!empty($user['instructor_id'])) {
                $stmt = $db->prepare("UPDATE instructors SET user_id = NULL WHERE id = ?");
                $stmt->execute([$user['instructor_id']]);
            }

            // 3. Deletar tokens de ativação relacionados
            $stmt = $db->prepare("DELETE FROM account_activation_tokens WHERE user_id = ?");
            $stmt->execute([$id]);

            // 4. Deletar roles do usuário
            $stmt = $db->prepare("DELETE FROM usuario_roles WHERE usuario_id = ?");
            $stmt->execute([$id]);

            // 5. Deletar tokens de reset de senha (se houver tabela)
            $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->execute([$id]);

            // 6. Registrar auditoria antes de deletar
            $this->auditService->logDelete('usuarios', $id, $dataBefore);

            // 7. Deletar o usuário
            $userModel->delete($id);

            $db->commit();

            $_SESSION['success'] = 'Usuário excluído com sucesso!';
        } catch (\Exception $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            error_log("Erro ao excluir usuário: " . $e->getMessage());
            $_SESSION['error'] = 'Erro ao excluir usuário: ' . $e->getMessage();
        }

        redirect(base_url('usuarios'));
    }
}
