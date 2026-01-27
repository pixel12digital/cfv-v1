<?php

namespace App\Services;

use App\Models\User;
use App\Config\Constants;

class AuthService
{
    private $lastAttemptFailureReason = 'credentials_invalid';

    public function attempt($email, $password)
    {
        $this->lastAttemptFailureReason = 'credentials_invalid';
        $user = User::findByEmail($email);
        
        if (!$user) {
            $this->lastAttemptFailureReason = 'user_not_found';
            return null;
        }
        if (!password_verify($password, $user['password'] ?? '')) {
            $this->lastAttemptFailureReason = 'wrong_password';
            return null;
        }
        if (($user['status'] ?? '') !== 'ativo') {
            $this->lastAttemptFailureReason = 'inactive';
            return null;
        }

        return $user;
    }

    public function getLastAttemptFailureReason()
    {
        return $this->lastAttemptFailureReason;
    }

    public function login($user)
    {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_type'] = $user['tipo'] ?? null;       // compatível com legado (includes/auth createSession)
        $_SESSION['cfc_id'] = $user['cfc_id'] ?? Constants::CFC_ID_DEFAULT;
        $_SESSION['user_cfc_id'] = $user['cfc_id'] ?? null;    // compatível com legado
        $_SESSION['must_change_password'] = !empty($user['must_change_password']) && $user['must_change_password'] == 1;
        $_SESSION['last_activity'] = time(); // compatível com legacy (includes/auth.php isLoggedIn)
        
        // Definir perfis disponíveis do usuário (RBAC quando existir, fallback para tipo)
        $roles = User::getUserRoles($user['id']);
        $availableRoles = [];

        if (!empty($roles)) {
            // $roles pode vir como array de arrays ['role' => 'ADMIN', ...]
            foreach ($roles as $role) {
                if (is_array($role) && isset($role['role'])) {
                    $availableRoles[] = $role['role'];
                } elseif (is_string($role)) {
                    $availableRoles[] = $role;
                }
            }
        }

        // Fallback para o campo 'tipo' quando não houver RBAC configurado
        if (empty($availableRoles)) {
            $tipo = strtolower($user['tipo'] ?? '');
            $roleMap = [
                'admin'      => Constants::ROLE_ADMIN,
                'secretaria' => Constants::ROLE_SECRETARIA,
                'instrutor'  => Constants::ROLE_INSTRUTOR,
                'aluno'      => Constants::ROLE_ALUNO,
            ];

            $fallbackRole = $roleMap[$tipo] ?? Constants::ROLE_ALUNO;
            $availableRoles = [$fallbackRole];

            // Log para debug
            error_log('[AuthService] Usuário sem roles na tabela usuario_roles. Usando tipo: ' . $tipo . ' -> role: ' . $fallbackRole);
        }

        // Padronizar na sessão como lista de strings (perfis disponíveis)
        $_SESSION['available_roles'] = $availableRoles;

        // Definir papel atual mantendo comportamento legado (web/painel)
        // current_role continua sendo a base de decisão para quem ainda não usa active_role
        $currentRole = $_SESSION['current_role'] ?? null;
        if (!$currentRole) {
            // Se havia um último papel salvo compatível, reutilizar
            $lastRole = $_SESSION['last_role'] ?? null;
            if ($lastRole && in_array($lastRole, $availableRoles, true)) {
                $currentRole = $lastRole;
            } else {
                // Caso contrário, usar o primeiro disponível
                $currentRole = $availableRoles[0];
            }
        }

        $_SESSION['current_role'] = $currentRole;

        // Novo: modo ativo (active_role) – por padrão, segue o comportamento atual do painel/web
        // PWA/App poderá sobrescrever esse valor conforme o contexto (ex.: sempre INSTRUTOR)
        if (empty($_SESSION['active_role']) || !in_array($_SESSION['active_role'], $availableRoles, true)) {
            $_SESSION['active_role'] = $currentRole;
        }
    }

    public function logout()
    {
        session_destroy();
        session_start();
    }

    public function switchRole($role)
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $availableRoles = $_SESSION['available_roles'] ?? [];
        if (empty($availableRoles)) {
            return false;
        }

        // Normalizar lista de perfis (aceita tanto ['ADMIN', 'INSTRUTOR'] quanto [['role' => 'ADMIN'], ...])
        $normalized = [];
        foreach ($availableRoles as $userRole) {
            if (is_array($userRole) && isset($userRole['role'])) {
                $normalized[] = $userRole['role'];
            } elseif (is_string($userRole)) {
                $normalized[] = $userRole;
            }
        }

        if (!in_array($role, $normalized, true)) {
            return false;
        }

        // Atualizar role atual e modo ativo
        $_SESSION['current_role'] = $role;
        $_SESSION['active_role'] = $role;
        $_SESSION['last_role'] = $role;

        // Memorizar último modo em cookie simples (sem mexer em modelo de dados)
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        @setcookie('last_active_role', $role, time() + (365 * 24 * 60 * 60), '/', '', $isHttps, true);

        return true;
    }
}
