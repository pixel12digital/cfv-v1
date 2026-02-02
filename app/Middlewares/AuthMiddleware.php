<?php

namespace App\Middlewares;

use App\Middlewares\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . base_url('login'));
            exit;
        }
        
        // Mobile: forçar INSTRUTOR quando usuário tiver esse perfil (bloquear alternância)
        if (function_exists('is_mobile_request') && is_mobile_request()) {
            $availableRoles = $_SESSION['available_roles'] ?? [];
            $normalized = [];
            foreach ($availableRoles as $r) {
                $code = is_array($r) ? ($r['role'] ?? null) : $r;
                if ($code) {
                    $normalized[] = strtoupper((string) $code);
                }
            }
            if (in_array('INSTRUTOR', $normalized, true)) {
                $_SESSION['active_role'] = 'INSTRUTOR';
                $_SESSION['current_role'] = 'INSTRUTOR';
            }
        }
        
        // Headers anti-cache para páginas autenticadas (segurança PWA)
        // Previne cache de HTML com dados sensíveis
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        return true;
    }
}
