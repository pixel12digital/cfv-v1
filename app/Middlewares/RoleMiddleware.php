<?php

namespace App\Middlewares;

use App\Middlewares\MiddlewareInterface;

class RoleMiddleware implements MiddlewareInterface
{
    private $allowedRoles = [];

    public function __construct(...$roles)
    {
        $this->allowedRoles = $roles;
    }

    public function handle(): bool
    {
        // Determinar o papel efetivo do usuário:
        // 1) Prioriza active_role (modo ativo)
        // 2) Fallback para current_role (comportamento legado)
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;

        if (empty($role)) {
            header('Location: ' . base_url('login'));
            exit;
        }

        if (!in_array($role, $this->allowedRoles, true)) {
            http_response_code(403);
            echo "Acesso negado";
            return false;
        }

        return true;
    }
}
