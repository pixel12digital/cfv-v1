<?php

namespace App\Services;

use App\Config\Database;

class PermissionService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function hasPermission($module, $action)
    {
        // Determinar o papel efetivo do usuário:
        // 1) Prioriza active_role (modo ativo)
        // 2) Fallback para current_role (comportamento legado)
        $role = $_SESSION['active_role'] ?? $_SESSION['current_role'] ?? null;

        if (empty($_SESSION['user_id']) || empty($role)) {
            return false;
        }

        // ADMIN tem todas as permissões
        if ($role === 'ADMIN') {
            return true;
        }

        $sql = "SELECT COUNT(*) as count
                FROM role_permissoes rp
                INNER JOIN permissoes p ON rp.permissao_id = p.id
                WHERE rp.role = ? AND p.modulo = ? AND p.acao = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$role, $module, $action]);
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    public static function check($module, $action)
    {
        $service = new self();
        return $service->hasPermission($module, $action);
    }
}
