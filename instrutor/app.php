<?php
/**
 * Página de entrada do PWA para Instrutores
 * Retorna 200 OK e redireciona via JavaScript (requisito PWA)
 * Redireciona para login se não estiver autenticado
 * Redireciona para dashboard se estiver autenticado
 */

require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

// Determinar destino do redirect
$redirectUrl = '../login.php?type=instrutor';

if (isLoggedIn()) {
    $user = getCurrentUser();

    // Normalizar lista de perfis disponíveis na sessão
    $availableRoles = $_SESSION['available_roles'] ?? [];

    // Se ainda não há available_roles (login legado antigo), tentar derivar a partir do tipo
    if (empty($availableRoles) && $user) {
        $tipo = strtolower($user['tipo'] ?? '');
        $roleMap = [
            'admin'      => 'ADMIN',
            'secretaria' => 'SECRETARIA',
            'instrutor'  => 'INSTRUTOR',
            'aluno'      => 'ALUNO',
        ];

        if (isset($roleMap[$tipo])) {
            $availableRoles = [$roleMap[$tipo]];
        }

        $_SESSION['available_roles'] = $availableRoles;
    }

    // Normalizar para array de strings
    $normalizedRoles = [];
    foreach ($availableRoles as $role) {
        if (is_array($role) && isset($role['role'])) {
            $normalizedRoles[] = $role['role'];
        } elseif (is_string($role)) {
            $normalizedRoles[] = $role;
        }
    }

    $hasInstructorProfile = in_array('INSTRUTOR', $normalizedRoles, true);

    if ($user && $hasInstructorProfile) {
        // EXPERIÊNCIA PWA: sempre entrar como INSTRUTOR quando o usuário tiver esse perfil
        $_SESSION['active_role'] = 'INSTRUTOR';

        // Opcionalmente alinhar current_role para que o restante do sistema enxergue o mesmo modo
        $_SESSION['current_role'] = $_SESSION['current_role'] ?? 'INSTRUTOR';

        // Usuário com perfil de instrutor - ir para dashboard de instrutor
        $redirectUrl = 'dashboard.php';
    } else {
        // Usuário logado mas sem perfil de instrutor - fazer logout e ir para login específico de instrutor
        if (function_exists('logout')) {
            logout();
        } else {
            session_destroy();
        }
        $redirectUrl = '../login.php?type=instrutor';
    }
}

// Retornar 200 OK com redirect via JavaScript (requisito PWA)
http_response_code(200);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecionando...</title>
    <script>
        // Redirect imediato via JavaScript (mantém status 200)
        window.location.replace('<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>');
    </script>
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <p>Redirecionando...</p>
    <p>Se não for redirecionado automaticamente, <a href="<?php echo htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8'); ?>">clique aqui</a>.</p>
</body>
</html>
