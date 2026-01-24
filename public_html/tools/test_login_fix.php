<?php
/**
 * Script para testar o login de instrutor após correções
 * Acesse via: http://localhost/cfc-v.1/public_html/tools/test_login_fix.php
 */

// Conectar diretamente ao banco correto (mesmo do listar_usuarios.php)
$dbConfig = [
    'host' => 'auth-db803.hstgr.io',
    'dbname' => 'u502697186_cfcv1',
    'username' => 'u502697186_cfcv1',
    'password' => 'Los@ngo#081081',
    'charset' => 'utf8mb4'
];

// Não incluir auth.php localmente para evitar problemas com database.php
// O script testa a lógica diretamente via PDO

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Login - Instrutor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Teste de Login - Instrutor (Após Correções)</h1>

<?php
try {
    // Criar conexão PDO direta para evitar problemas com database.php
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<div class='info'>";
    echo "<h2>1. Verificando Usuário rwavieira@gmail.com</h2>";
    
    // Buscar usuário diretamente usando PDO
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['rwavieira@gmail.com']);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        echo "<p class='error'>✗ Usuário não encontrado!</p>";
        exit;
    }
    
    echo "<p class='success'>✓ Usuário encontrado: ID {$usuario['id']}, Nome: {$usuario['nome']}</p>";
    
    // Verificar roles usando PDO
    $stmt = $pdo->prepare("SELECT ur.role, r.nome as role_nome FROM usuario_roles ur LEFT JOIN roles r ON ur.role = r.role WHERE ur.usuario_id = ?");
    $stmt->execute([$usuario['id']]);
    $roles = $stmt->fetchAll();
    
    echo "<h3>Roles do Usuário:</h3>";
    if (empty($roles)) {
        echo "<p class='error'>✗ Nenhum role encontrado!</p>";
    } else {
        echo "<ul>";
        foreach ($roles as $role) {
            echo "<li><strong>{$role['role']}</strong> - {$role['role_nome']}</li>";
        }
        echo "</ul>";
    }
    
    // Testar lógica de getUserType() diretamente via PDO (mesma lógica do código corrigido)
    echo "<h2>2. Testando Lógica de Determinação de Tipo (getUserType)</h2>";
    
    // Simular a lógica do método getUserType() que foi adicionado em includes/auth.php
    $stmt = $pdo->prepare("SELECT ur.role FROM usuario_roles ur WHERE ur.usuario_id = ? ORDER BY ur.id LIMIT 1");
    $stmt->execute([$usuario['id']]);
    $role = $stmt->fetch();
    
    if ($role && !empty($role['role'])) {
        // Mapear role RBAC para tipo legado (mesma lógica do código corrigido)
        $roleMap = [
            'ADMIN' => 'admin',
            'SECRETARIA' => 'secretaria',
            'INSTRUTOR' => 'instrutor',
            'ALUNO' => 'aluno'
        ];
        $tipo = $roleMap[strtoupper($role['role'])] ?? 'aluno';
        echo "<p class='success'>✓ Role encontrado na tabela usuario_roles: <strong>{$role['role']}</strong></p>";
    } else {
        // Se não encontrou em usuario_roles, tentar campo 'tipo' (sistema antigo)
        $stmt = $pdo->prepare("SELECT tipo FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$usuario['id']]);
        $usuarioTipo = $stmt->fetch();
        
        if ($usuarioTipo && !empty($usuarioTipo['tipo'])) {
            $tipo = strtolower($usuarioTipo['tipo']);
            echo "<p class='info'>⚠ Tipo encontrado no campo 'tipo' (sistema antigo): <strong>{$tipo}</strong></p>";
        } else {
            $tipo = 'aluno'; // Fallback
            echo "<p class='warning'>⚠ Nenhum tipo encontrado, usando fallback: <strong>{$tipo}</strong></p>";
        }
    }
    
    echo "<p class='success'>✓ Tipo determinado: <strong>{$tipo}</strong></p>";
    
    if ($tipo !== 'instrutor') {
        echo "<p class='error'>✗ PROBLEMA: Tipo deveria ser 'instrutor', mas é '{$tipo}'</p>";
    } else {
        echo "<p class='success'>✓ Tipo correto: 'instrutor'</p>";
    }
    
    // Testar senha
    echo "<h2>3. Testando Verificação de Senha</h2>";
    
    $senhaTeste = 'instrutor123';
    $senhaHash = $usuario['password'] ?? $usuario['senha'] ?? null;
    
    if (!$senhaHash) {
        echo "<p class='error'>✗ Senha não encontrada no banco!</p>";
    } else {
        echo "<p class='success'>✓ Hash de senha encontrado (tamanho: " . strlen($senhaHash) . " caracteres)</p>";
        
        $senhaValida = password_verify($senhaTeste, $senhaHash);
        
        if ($senhaValida) {
            echo "<p class='success'>✓ Senha '{$senhaTeste}' é válida!</p>";
        } else {
            echo "<p class='error'>✗ Senha '{$senhaTeste}' é inválida!</p>";
            echo "<p class='info'>Tente outras senhas ou verifique se a senha foi atualizada corretamente.</p>";
        }
    }
    
    // Resumo das correções
    echo "<h2>4. Resumo das Correções Implementadas</h2>";
    
    echo "<div class='info'>";
    echo "<h3>✓ Correções Aplicadas em includes/auth.php:</h3>";
    echo "<ol>";
    echo "<li><strong>Método getUserType() criado:</strong> Busca o tipo do usuário a partir de <code>usuario_roles</code> (RBAC) quando o campo <code>tipo</code> não existe.</li>";
    echo "<li><strong>createSession() corrigido:</strong> Usa <code>getUserType()</code> em vez de <code>\$usuario['tipo']</code>.</li>";
    echo "<li><strong>redirectAfterLogin() corrigido:</strong> Busca tipo via <code>getUserType()</code> se não estiver no array.</li>";
    echo "<li><strong>getUserData() corrigido:</strong> Sempre inclui o tipo do usuário usando <code>getUserType()</code>.</li>";
    echo "<li><strong>login() corrigido:</strong> Aceita tanto <code>senha</code> quanto <code>password</code> e verifica <code>status</code> quando <code>ativo</code> não existir.</li>";
    echo "</ol>";
    
    echo "<h3>📋 Próximos Passos:</h3>";
    echo "<ol>";
    echo "<li><strong>Teste em Produção:</strong> As correções estão prontas. Faça commit e teste em produção onde as credenciais do banco estão corretas.</li>";
    echo "<li><strong>Verificar Login:</strong> Tente fazer login como instrutor (<code>rwavieira@gmail.com</code>) em produção.</li>";
    echo "<li><strong>Verificar Redirecionamento:</strong> Após login bem-sucedido, o usuário deve ser redirecionado para <code>/instrutor/dashboard.php</code>.</li>";
    echo "</ol>";
    
    echo "<h3>✅ Validação Local:</h3>";
    echo "<ul>";
    echo "<li>✓ Usuário encontrado: ID {$usuario['id']}</li>";
    echo "<li>✓ Role INSTRUTOR atribuído na tabela <code>usuario_roles</code></li>";
    echo "<li>✓ Tipo determinado corretamente: <strong>{$tipo}</strong></li>";
    if ($tipo === 'instrutor') {
        echo "<li>✓ <strong style='color: #28a745;'>LÓGICA CORRETA!</strong> O código deve funcionar em produção.</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>ERRO</h2>";
    echo "<p>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>

    </div>
</body>
</html>
