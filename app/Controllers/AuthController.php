<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\PasswordResetToken;
use App\Models\AccountActivationToken;
use App\Models\Cfc;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Config\Constants;
use App\Config\Database;

class AuthController extends Controller
{
    private $authService;
    private $emailService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Helper para redirecionar para o dashboard correto baseado no tipo do usuário
     */
    private function redirectToUserDashboard($userId = null)
    {
        if (!$userId) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        
        if (!$userId) {
            redirect(base_url('/login'));
            return;
        }
        
        $userModel = new User();
        $user = $userModel->find($userId);
        
        if (!$user) {
            redirect(base_url('/login'));
            return;
        }
        
        $tipo = strtolower($user['tipo'] ?? '');
        
        switch ($tipo) {
            case 'admin':
            case 'secretaria':
                redirect(base_url('/admin/index.php'));
                break;
            case 'instrutor':
                redirect(base_url('/instrutor/dashboard.php'));
                break;
            case 'aluno':
                redirect(base_url('/aluno/dashboard.php'));
                break;
            default:
                // Fallback para dashboard genérico
                redirect(base_url('/dashboard'));
        }
    }

    public function showLogin()
    {
        // Verificar se há sessão ativa E se o usuário realmente existe e está ativo
        if (!empty($_SESSION['user_id'])) {
            $userModel = new User();
            $user = $userModel->find($_SESSION['user_id']);
            
            // Só redirecionar para dashboard se o usuário existir e estiver ativo
            if ($user && $user['status'] === 'ativo') {
                // CORREÇÃO: Redirecionar para o dashboard correto baseado no tipo do usuário
                // Não usar /dashboard genérico, usar os dashboards legados específicos
                $tipo = strtolower($user['tipo'] ?? '');
                
                switch ($tipo) {
                    case 'admin':
                    case 'secretaria':
                        redirect(base_url('/admin/index.php'));
                        break;
                    case 'instrutor':
                        redirect(base_url('/instrutor/dashboard.php'));
                        break;
                    case 'aluno':
                        redirect(base_url('/aluno/dashboard.php'));
                        break;
                    default:
                        // Se tipo desconhecido, usar dashboard genérico
                        redirect(base_url('/dashboard'));
                }
            } else {
                // Se usuário não existe ou está inativo, limpar sessão e mostrar login
                session_destroy();
                session_start();
            }
        }
        
        // Buscar CFC para exibir logo no login (sem sessão, usar padrão ou primeiro CFC)
        $cfcModel = new Cfc();
        $cfc = $cfcModel->find(Constants::CFC_ID_DEFAULT);
        
        // Se não encontrar pelo ID padrão, buscar o primeiro CFC (single-tenant)
        if (!$cfc) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM cfcs ORDER BY id ASC LIMIT 1");
            $cfc = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        // Verificar se tem logo do CFC e se o arquivo existe
        $logoUrl = null;
        if ($cfc && !empty($cfc['logo_path'])) {
            $filepath = dirname(__DIR__, 2) . '/' . $cfc['logo_path'];
            if (file_exists($filepath)) {
                $logoUrl = base_url('login/cfc-logo');
            }
        }
        // Fallback: logo padrão quando o CFC não tem logo (evita ficar só texto "CFC")
        if (empty($logoUrl)) {
            $logoUrl = asset_url('logo.png');
        }
        
        $data = [
            'logoUrl' => $logoUrl
        ];
        
        $this->viewRaw('auth/login', $data);
    }
    
    /**
     * Servir logo do CFC no login (rota pública, sem autenticação)
     */
    public function cfcLogo()
    {
        // Buscar CFC (mesma lógica do showLogin)
        $cfcModel = new Cfc();
        $cfc = $cfcModel->find(Constants::CFC_ID_DEFAULT);
        
        if (!$cfc) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM cfcs ORDER BY id ASC LIMIT 1");
            $cfc = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$cfc || empty($cfc['logo_path'])) {
            http_response_code(404);
            header('Content-Type: text/plain');
            exit('Logo não encontrado');
        }
        
        $filepath = dirname(__DIR__, 2) . '/' . $cfc['logo_path'];
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            header('Content-Type: text/plain');
            exit('Logo não encontrado');
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

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->showLogin();
            return;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['error'] = 'Email e senha são obrigatórios';
            redirect(base_url('/login'));
        }

        $user = $this->authService->attempt($email, $password);

        if ($user) {
            $this->authService->login($user);
            
            // Verificar se precisa trocar senha
            if (!empty($user['must_change_password']) && $user['must_change_password'] == 1) {
                $_SESSION['warning'] = 'Por segurança, você precisa alterar sua senha no primeiro acesso.';
                redirect(base_url('/change-password'));
            }
            
            // CORREÇÃO: Redirecionar para o dashboard correto baseado no tipo do usuário
            $this->redirectToUserDashboard($user['id']);
        } else {
            $_SESSION['error'] = 'Credenciais inválidas';
            redirect(base_url('/login'));
        }
    }

    public function logout()
    {
        $this->authService->logout();
        redirect(base_url('/login'));
    }

    /**
     * Tela de recuperação de senha
     */
    public function showForgotPassword()
    {
        if (!empty($_SESSION['user_id'])) {
            // CORREÇÃO: Redirecionar para o dashboard correto baseado no tipo do usuário
            $this->redirectToUserDashboard();
            return;
        }
        $this->viewRaw('auth/forgot-password');
    }

    /**
     * Processa solicitação de recuperação de senha
     */
    public function forgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->showForgotPassword();
            return;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'E-mail inválido.';
            redirect(base_url('/forgot-password'));
        }

        $userModel = new User();
        $user = $userModel->findByEmail($email);

        // Sempre retornar sucesso (segurança - não revelar se email existe)
        if ($user && $user['status'] === 'ativo') {
            try {
                $tokenModel = new PasswordResetToken();
                $token = $tokenModel->createToken($user['id'], 1); // 1 hora

                if ($token) {
                    $resetUrl = base_url("/reset-password?token={$token}");
                    $this->emailService->sendPasswordReset($email, $token, $resetUrl);
                }
            } catch (\Exception $e) {
                error_log("Erro ao enviar e-mail de recuperação: " . $e->getMessage());
            }
        }

        $_SESSION['success'] = 'Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.';
        redirect(base_url('/login'));
    }

    /**
     * Tela de redefinição de senha
     */
    public function showResetPassword()
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $_SESSION['error'] = 'Token inválido.';
            redirect(base_url('/forgot-password'));
        }

        $tokenModel = new PasswordResetToken();
        $tokenData = $tokenModel->findValidToken($token);

        if (!$tokenData) {
            $_SESSION['error'] = 'Token inválido ou expirado.';
            redirect(base_url('/forgot-password'));
        }

        $data = [
            'token' => $token,
            'user' => $tokenData
        ];

        $this->viewRaw('auth/reset-password', $data);
    }

    /**
     * Processa redefinição de senha
     */
    public function resetPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            redirect(base_url('/forgot-password'));
        }

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($token) || empty($password) || empty($passwordConfirm)) {
            $_SESSION['error'] = 'Preencha todos os campos.';
            redirect(base_url("/reset-password?token={$token}"));
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['error'] = 'As senhas não coincidem.';
            redirect(base_url("/reset-password?token={$token}"));
        }

        // Validar política de senha (mínimo 8 caracteres)
        if (strlen($password) < 8) {
            $_SESSION['error'] = 'A senha deve ter no mínimo 8 caracteres.';
            redirect(base_url("/reset-password?token={$token}"));
        }

        $tokenModel = new PasswordResetToken();
        $tokenData = $tokenModel->findValidToken($token);

        if (!$tokenData) {
            $_SESSION['error'] = 'Token inválido ou expirado.';
            redirect(base_url('/forgot-password'));
        }

        // Atualizar senha
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $userModel = new User();
        $userModel->updatePassword($tokenData['user_id'], $hashedPassword);

        // Marcar token como usado
        $tokenModel->markAsUsed($token);

        $_SESSION['success'] = 'Senha redefinida com sucesso! Faça login com sua nova senha.';
        redirect(base_url('/login'));
    }

    /**
     * Tela de definir senha no primeiro acesso (onboarding após /start?token=...).
     * Acesso permitido só quando há sessão de onboarding (onboarding_user_id + force_password_change).
     */
    public function showDefinePassword()
    {
        if (empty($_SESSION['onboarding_user_id']) || empty($_SESSION['force_password_change'])) {
            redirect(base_url('/login'));
            return;
        }
        $userModel = new User();
        $user = $userModel->find($_SESSION['onboarding_user_id']);
        if (!$user || ($user['status'] ?? '') !== 'ativo') {
            unset($_SESSION['onboarding_user_id'], $_SESSION['force_password_change']);
            redirect(base_url('/login'));
            return;
        }
        $data = ['user' => $user];
        $this->viewRaw('auth/define-password', $data);
    }

    /**
     * Processa definição de senha no primeiro acesso.
     * Atualiza senha, remove must_change_password, faz login e redireciona para /install.
     */
    public function definePassword()
    {
        if (empty($_SESSION['onboarding_user_id']) || empty($_SESSION['force_password_change'])) {
            redirect(base_url('/login'));
            return;
        }
        $userId = (int) $_SESSION['onboarding_user_id'];
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->showDefinePassword();
            return;
        }
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
            redirect(base_url('/define-password'));
        }
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
        if (empty($newPassword) || empty($newPasswordConfirm)) {
            $_SESSION['error'] = 'Preencha todos os campos.';
            redirect(base_url('/define-password'));
        }
        if ($newPassword !== $newPasswordConfirm) {
            $_SESSION['error'] = 'As senhas não coincidem.';
            redirect(base_url('/define-password'));
        }
        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'A senha deve ter no mínimo 8 caracteres.';
            redirect(base_url('/define-password'));
        }
        $userModel = new User();
        $user = $userModel->find($userId);
        if (!$user || ($user['status'] ?? '') !== 'ativo') {
            unset($_SESSION['onboarding_user_id'], $_SESSION['force_password_change']);
            redirect(base_url('/login'));
            return;
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $userModel->updatePassword($userId, $hashedPassword);
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE usuarios SET must_change_password = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        unset($_SESSION['onboarding_user_id'], $_SESSION['force_password_change']);
        $user = $userModel->find($userId);
        $this->authService->login($user);
        $_SESSION['success'] = 'Senha definida com sucesso! Agora você pode instalar o app.';
        redirect(base_url('/install'));
    }

    /**
     * Tela de alteração de senha (usuário logado)
     */
    public function showChangePassword()
    {
        if (empty($_SESSION['user_id'])) {
            redirect(base_url('/login'));
        }

        $data = [
            'pageTitle' => 'Alterar Senha'
        ];
        $this->view('auth/change-password', $data);
    }

    /**
     * Processa alteração de senha (usuário logado)
     */
    public function changePassword()
    {
        if (empty($_SESSION['user_id'])) {
            redirect(base_url('/login'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->showChangePassword();
            return;
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('/change-password'));
        }

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($newPasswordConfirm)) {
            $_SESSION['error'] = 'Preencha todos os campos.';
            redirect(base_url('/change-password'));
        }

        if ($newPassword !== $newPasswordConfirm) {
            $_SESSION['error'] = 'As novas senhas não coincidem.';
            redirect(base_url('/change-password'));
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'A senha deve ter no mínimo 8 caracteres.';
            redirect(base_url('/change-password'));
        }

        // Verificar senha atual
        $userModel = new User();
        $user = $userModel->find($_SESSION['user_id']);

        if (!$user) {
            error_log("[CHANGE_PASSWORD] Usuário não encontrado. ID: " . $_SESSION['user_id']);
            $_SESSION['error'] = 'Usuário não encontrado.';
            redirect(base_url('/change-password'));
        }

        if (empty($user['password'])) {
            error_log("[CHANGE_PASSWORD] Senha vazia no banco. User ID: " . $_SESSION['user_id']);
            $_SESSION['error'] = 'Erro ao verificar senha. Contate o administrador.';
            redirect(base_url('/change-password'));
        }

        // Verificar senha atual
        $passwordValid = password_verify($currentPassword, $user['password']);
        
        // Se a senha não for válida, mas o usuário está obrigado a trocar senha
        // (must_change_password = 1), isso significa que ele acabou de fazer login
        // com a senha temporária. Nesse caso, permitir a alteração mesmo sem validar
        // a senha atual, pois o login já validou que ele tem acesso.
        $mustChangePassword = !empty($user['must_change_password']) && $user['must_change_password'] == 1;
        
        if (!$passwordValid && !$mustChangePassword) {
            error_log("[CHANGE_PASSWORD] Senha atual incorreta. User ID: " . $_SESSION['user_id'] . 
                      " | Email: " . ($user['email'] ?? 'N/A'));
            $_SESSION['error'] = 'Senha atual incorreta.';
            redirect(base_url('/change-password'));
        }
        
        // Se must_change_password está ativo mas a senha não é válida, logar para debug
        if (!$passwordValid && $mustChangePassword) {
            error_log("[CHANGE_PASSWORD] Senha atual não corresponde, mas must_change_password=1. " .
                      "Permitindo alteração pois login foi validado. User ID: " . $_SESSION['user_id'] . 
                      " | Email: " . ($user['email'] ?? 'N/A'));
        }

        // Atualizar senha e remover flag de troca obrigatória
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE usuarios SET password = ?, must_change_password = 0 WHERE id = ?");
        $stmt->execute([$hashedPassword, $_SESSION['user_id']]);

        $_SESSION['success'] = 'Senha alterada com sucesso!';
        
        // Se estava obrigado a trocar, redirecionar para dashboard correto
        if (!empty($user['must_change_password']) && $user['must_change_password'] == 1) {
            $this->redirectToUserDashboard($user['id']);
            return;
        }
        
        redirect(base_url('/change-password'));
    }

    /**
     * Mostra tela de ativação de conta
     */
    public function showActivateAccount()
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $_SESSION['error'] = 'Token de ativação não fornecido.';
            redirect(base_url('/login'));
        }

        // Validar token
        $tokenHash = hash('sha256', $token);
        $tokenModel = new AccountActivationToken();
        $tokenData = $tokenModel->findByTokenHash($tokenHash);

        if (!$tokenData) {
            $_SESSION['error'] = 'Token de ativação inválido ou expirado. Solicite um novo link.';
            redirect(base_url('/login'));
        }

        // Buscar usuário
        $userModel = new User();
        $user = $userModel->find($tokenData['user_id']);

        if (!$user || $user['status'] !== 'ativo') {
            $_SESSION['error'] = 'Usuário não encontrado ou inativo.';
            redirect(base_url('/login'));
        }

        $data = [
            'token' => $token,
            'user' => $user
        ];

        $this->view('auth/activate-account', $data);
    }

    /**
     * Processa ativação de conta (definir senha)
     */
    public function activateAccount()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->showActivateAccount();
            return;
        }

        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            $_SESSION['error'] = 'Token CSRF inválido.';
            redirect(base_url('/login'));
        }

        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

        if (empty($token)) {
            $_SESSION['error'] = 'Token de ativação não fornecido.';
            redirect(base_url('/login'));
        }

        if (empty($newPassword) || empty($newPasswordConfirm)) {
            $_SESSION['error'] = 'Preencha todos os campos.';
            redirect(base_url("/ativar-conta?token={$token}"));
        }

        if ($newPassword !== $newPasswordConfirm) {
            $_SESSION['error'] = 'As senhas não coincidem.';
            redirect(base_url("/ativar-conta?token={$token}"));
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'A senha deve ter no mínimo 8 caracteres.';
            redirect(base_url("/ativar-conta?token={$token}"));
        }

        // Validar token
        $tokenHash = hash('sha256', $token);
        $tokenModel = new AccountActivationToken();
        $tokenData = $tokenModel->findByTokenHash($tokenHash);

        if (!$tokenData) {
            $_SESSION['error'] = 'Token de ativação inválido ou expirado. Solicite um novo link.';
            redirect(base_url('/login'));
        }

        // Buscar usuário
        $userModel = new User();
        $user = $userModel->find($tokenData['user_id']);

        if (!$user || $user['status'] !== 'ativo') {
            $_SESSION['error'] = 'Usuário não encontrado ou inativo.';
            redirect(base_url('/login'));
        }

        try {
            $db = Database::getInstance()->getConnection();
            $db->beginTransaction();

            // Atualizar senha e remover flag de troca obrigatória
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
                UPDATE usuarios 
                SET password = ?, must_change_password = 0 
                WHERE id = ?
            ");
            $stmt->execute([$hashedPassword, $user['id']]);

            // Marcar token como usado
            $tokenModel->markAsUsed($tokenData['id']);

            $db->commit();

            $_SESSION['success'] = 'Conta ativada com sucesso! Você já pode fazer login.';
            redirect(base_url('/login'));
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Erro ao ativar conta: ' . $e->getMessage();
            redirect(base_url("/ativar-conta?token={$token}"));
        }
    }
}
