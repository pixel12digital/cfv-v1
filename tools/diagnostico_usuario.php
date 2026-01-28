<?php
/**
 * Diagnóstico de usuário - verificar status de senha e tokens
 * USO: php tools/diagnostico_usuario.php cfcbomconselho@hotmail.com
 * OU via browser: /tools/diagnostico_usuario.php?email=cfcbomconselho@hotmail.com
 */

// Carregar configuração
$configPath = __DIR__ . '/../includes/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
} else {
    // Fallback para conexão direta
    $host = 'localhost';
    $dbname = 'cfc_sistema';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Erro de conexão: " . $e->getMessage());
    }
}

// Obter email do argumento ou query string
$email = $argv[1] ?? $_GET['email'] ?? null;

if (!$email) {
    die("Uso: php diagnostico_usuario.php <email>\nOu: ?email=<email>\n");
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE USUÁRIO ===\n";
echo "Email: $email\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 50) . "\n\n";

// Obter conexão
if (!isset($pdo)) {
    if (class_exists('App\Config\Database')) {
        $pdo = \App\Config\Database::getInstance()->getConnection();
    } elseif (isset($db)) {
        $pdo = $db;
    } else {
        die("Não foi possível obter conexão com o banco.\n");
    }
}

// 1. Buscar usuário
echo "1. DADOS DO USUÁRIO\n";
echo str_repeat("-", 40) . "\n";

$stmt = $pdo->prepare("SELECT id, nome, email, tipo, status, cfc_id, 
                              password IS NOT NULL AND password != '' as has_password,
                              LENGTH(password) as password_length,
                              LEFT(password, 7) as password_prefix,
                              must_change_password,
                              created_at, updated_at
                       FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "❌ USUÁRIO NÃO ENCONTRADO!\n";
    exit;
}

echo "ID: {$user['id']}\n";
echo "Nome: {$user['nome']}\n";
echo "Email: {$user['email']}\n";
echo "Tipo: {$user['tipo']}\n";
echo "Status: {$user['status']}\n";
echo "CFC ID: {$user['cfc_id']}\n";
echo "Tem senha: " . ($user['has_password'] ? 'SIM' : 'NÃO') . "\n";
echo "Tamanho da senha (hash): {$user['password_length']} chars\n";
echo "Prefixo do hash: {$user['password_prefix']}...\n";
echo "Must change password: " . ($user['must_change_password'] ? 'SIM' : 'NÃO') . "\n";
echo "Criado em: {$user['created_at']}\n";
echo "Atualizado em: {$user['updated_at']}\n";

// Verificar se o hash parece válido (bcrypt começa com $2y$)
if ($user['has_password']) {
    if (strpos($user['password_prefix'], '$2y$') === 0 || strpos($user['password_prefix'], '$2a$') === 0) {
        echo "✅ Hash de senha parece válido (bcrypt)\n";
    } else {
        echo "⚠️ Hash de senha NÃO parece ser bcrypt! Prefixo: {$user['password_prefix']}\n";
    }
}

// 2. Buscar roles
echo "\n2. ROLES DO USUÁRIO\n";
echo str_repeat("-", 40) . "\n";

$stmt = $pdo->prepare("SELECT ur.role, r.nome as role_name 
                       FROM usuario_roles ur 
                       LEFT JOIN roles r ON r.role = ur.role
                       WHERE ur.usuario_id = ?");
$stmt->execute([$user['id']]);
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($roles)) {
    echo "⚠️ Nenhum role encontrado!\n";
} else {
    foreach ($roles as $role) {
        echo "- {$role['role']} ({$role['role_name']})\n";
    }
}

// 3. Tokens de ativação
echo "\n3. TOKENS DE ATIVAÇÃO (account_activation_tokens)\n";
echo str_repeat("-", 40) . "\n";

$stmt = $pdo->prepare("SELECT id, LEFT(token_hash, 20) as token_prefix, 
                              expires_at, used_at, created_at,
                              expires_at > NOW() as is_valid,
                              used_at IS NOT NULL as is_used
                       FROM account_activation_tokens 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 5");
$stmt->execute([$user['id']]);
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tokens)) {
    echo "Nenhum token de ativação encontrado.\n";
} else {
    foreach ($tokens as $token) {
        $status = [];
        if ($token['is_used']) $status[] = '❌ USADO';
        if (!$token['is_valid']) $status[] = '❌ EXPIRADO';
        if (!$token['is_used'] && $token['is_valid']) $status[] = '✅ VÁLIDO';
        
        echo "Token ID: {$token['id']}\n";
        echo "  Hash prefix: {$token['token_prefix']}...\n";
        echo "  Criado: {$token['created_at']}\n";
        echo "  Expira: {$token['expires_at']}\n";
        echo "  Usado em: " . ($token['used_at'] ?: 'NÃO') . "\n";
        echo "  Status: " . implode(', ', $status) . "\n\n";
    }
}

// 4. Tokens de reset de senha
echo "\n4. TOKENS DE RESET DE SENHA (password_reset_tokens)\n";
echo str_repeat("-", 40) . "\n";

$stmt = $pdo->prepare("SELECT id, LEFT(token, 20) as token_prefix, 
                              expires_at, used_at, created_at
                       FROM password_reset_tokens 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 5");
$stmt->execute([$user['id']]);
$resetTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($resetTokens)) {
    echo "Nenhum token de reset encontrado.\n";
} else {
    foreach ($resetTokens as $token) {
        echo "Token ID: {$token['id']}\n";
        echo "  Token prefix: {$token['token_prefix']}...\n";
        echo "  Criado: {$token['created_at']}\n";
        echo "  Expira: {$token['expires_at']}\n";
        echo "  Usado em: " . ($token['used_at'] ?: 'NÃO') . "\n\n";
    }
}

// 5. Verificar token específico (se fornecido)
$tokenToCheck = $_GET['token'] ?? null;
if ($tokenToCheck) {
    echo "\n5. VERIFICAÇÃO DO TOKEN FORNECIDO\n";
    echo str_repeat("-", 40) . "\n";
    
    $tokenHash = hash('sha256', $tokenToCheck);
    echo "Token fornecido: " . substr($tokenToCheck, 0, 20) . "...\n";
    echo "Hash calculado: " . substr($tokenHash, 0, 20) . "...\n";
    
    $stmt = $pdo->prepare("SELECT * FROM account_activation_tokens WHERE token_hash = ?");
    $stmt->execute([$tokenHash]);
    $foundToken = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($foundToken) {
        echo "✅ Token encontrado no banco!\n";
        echo "  User ID: {$foundToken['user_id']}\n";
        echo "  Expira: {$foundToken['expires_at']}\n";
        echo "  Usado: " . ($foundToken['used_at'] ?: 'NÃO') . "\n";
    } else {
        echo "❌ Token NÃO encontrado no banco!\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "FIM DO DIAGNÓSTICO\n";
