<?php
/**
 * Script para listar todos os usuários do banco u502697186_cfcv1
 * Acesse via: http://localhost/cfc-v.1/public_html/tools/listar_usuarios.php
 */

// Conectar diretamente ao banco correto
$dbConfig = [
    'host' => 'auth-db803.hstgr.io',
    'dbname' => 'u502697186_cfcv1',
    'username' => 'u502697186_cfcv1',
    'password' => 'Los@ngo#081081',
    'charset' => 'utf8mb4'
];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Usuários - Banco u502697186_cfcv1</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .instrutor { background: #fff3cd !important; }
        .admin { background: #d1ecf1 !important; }
        .aluno { background: #d4edda !important; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .banco-info { background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Listagem de Usuários - Banco u502697186_cfcv1</h1>

<?php
try {
    // Conectar diretamente ao banco
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Verificar banco conectado
    $dbInfo = $pdo->query("SELECT DATABASE() as db_name")->fetch();
    $bancoAtual = $dbInfo['db_name'] ?? 'N/A';
    
    echo "<div class='banco-info'>";
    echo "<strong>✓ Banco de Dados Conectado:</strong> " . htmlspecialchars($bancoAtual) . "<br>";
    echo "<strong>Host:</strong> " . htmlspecialchars($dbConfig['host']) . "<br>";
    echo "<strong>Usuário:</strong> " . htmlspecialchars($dbConfig['username']);
    echo "</div>";
    
    // Verificar estrutura da tabela usuarios
    echo "<div class='info'>";
    echo "<h2>0. Estrutura da Tabela 'usuarios'</h2>";
    
    try {
        $stmt = $pdo->query("DESCRIBE usuarios");
        $colunas = $stmt->fetchAll();
        
        echo "<p class='success'>✓ Colunas encontradas na tabela 'usuarios':</p>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($colunas as $col) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro ao verificar estrutura: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // 1. Todos os usuários (sem filtro de tipo primeiro)
    echo "<div class='info'>";
    echo "<h2>1. Todos os Usuários</h2>";
    
    // Primeiro, verificar quais colunas existem
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios");
    $colunasExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Montar SELECT apenas com colunas que existem
    $colunasSelect = ['id', 'nome', 'email'];
    $colunasDisponiveis = ['id', 'nome', 'email', 'tipo', 'ativo', 'cpf', 'telefone', 'status', 'role', 'user_type'];
    $colunasParaSelect = array_intersect($colunasDisponiveis, $colunasExistentes);
    
    if (empty($colunasParaSelect)) {
        $colunasParaSelect = ['id', 'nome', 'email']; // Mínimo
    }
    
    $sql = "SELECT " . implode(', ', $colunasParaSelect) . " FROM usuarios ORDER BY id";
    $stmt = $pdo->query($sql);
    $todosUsuarios = $stmt->fetchAll();
    
    if (empty($todosUsuarios)) {
        echo "<p class='error'>✗ Nenhum usuário encontrado no banco de dados!</p>";
    } else {
        echo "<p class='success'>✓ Encontrados " . count($todosUsuarios) . " usuário(s)</p>";
        echo "<p class='info'>Colunas disponíveis: " . implode(', ', $colunasParaSelect) . "</p>";
        echo "<table>";
        echo "<tr>";
        foreach ($colunasParaSelect as $col) {
            echo "<th>" . htmlspecialchars(ucfirst($col)) . "</th>";
        }
        echo "</tr>";
        
        foreach ($todosUsuarios as $user) {
            $rowClass = '';
            // Tentar determinar tipo de várias formas
            $tipoUsuario = '';
            if (isset($user['tipo'])) {
                $tipoUsuario = strtolower($user['tipo']);
            } elseif (isset($user['role'])) {
                $tipoUsuario = strtolower($user['role']);
            } elseif (isset($user['user_type'])) {
                $tipoUsuario = strtolower($user['user_type']);
            }
            
            switch ($tipoUsuario) {
                case 'instrutor':
                    $rowClass = 'instrutor';
                    break;
                case 'admin':
                    $rowClass = 'admin';
                    break;
                case 'aluno':
                    $rowClass = 'aluno';
                    break;
            }
            
            echo "<tr class='{$rowClass}'>";
            foreach ($colunasParaSelect as $col) {
                $value = $user[$col] ?? 'N/A';
                if ($col === 'tipo' || $col === 'role' || $col === 'user_type') {
                    echo "<td><strong>" . htmlspecialchars($value) . "</strong></td>";
                } else {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // 2. Nota sobre sistema RBAC
    echo "<div class='info'>";
    echo "<h2>2. Sistema RBAC (Role-Based Access Control)</h2>";
    echo "<p class='info'>ℹ️ <strong>Importante:</strong> Este sistema usa RBAC. O tipo de usuário é determinado pela tabela <code>usuario_roles</code>, não por uma coluna <code>tipo</code> na tabela <code>usuarios</code>.</p>";
    echo "<p class='info'>Os roles disponíveis são: ADMIN, SECRETARIA, INSTRUTOR, ALUNO (veja seção 3).</p>";
    echo "</div>";
    
    // 3. Tabela 'roles' (RBAC)
    echo "<div class='info'>";
    echo "<h2>3. Tabela 'roles' (RBAC - Papéis Disponíveis)</h2>";
    
    try {
        $stmt = $pdo->query("SELECT * FROM roles ORDER BY role");
        $roles = $stmt->fetchAll();
        
        if (empty($roles)) {
            echo "<p class='warning'>⚠ Nenhum role encontrado na tabela 'roles'!</p>";
        } else {
            echo "<p class='success'>✓ Encontrados " . count($roles) . " role(s)</p>";
            echo "<table>";
            echo "<tr><th>Role</th><th>Nome</th><th>Descrição</th></tr>";
            foreach ($roles as $role) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($role['role']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($role['nome'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($role['descricao'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro ao consultar tabela 'roles': " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // 4. Tabela 'usuario_roles' (RBAC - Relacionamento Usuário-Role)
    echo "<div class='info'>";
    echo "<h2>4. Tabela 'usuario_roles' (RBAC - Usuários e seus Papéis)</h2>";
    
    try {
        $stmt = $pdo->query("
            SELECT ur.id, ur.usuario_id, ur.role, ur.created_at,
                   u.nome as usuario_nome, u.email as usuario_email, u.status as usuario_status,
                   r.nome as role_nome
            FROM usuario_roles ur
            LEFT JOIN usuarios u ON ur.usuario_id = u.id
            LEFT JOIN roles r ON ur.role = r.role
            ORDER BY ur.usuario_id, ur.role
        ");
        $usuarioRoles = $stmt->fetchAll();
        
        if (empty($usuarioRoles)) {
            echo "<p class='warning'>⚠ Nenhum registro na tabela 'usuario_roles' encontrado!</p>";
            echo "<p class='info'>Isso significa que nenhum usuário tem roles atribuídos. O sistema tentará usar o campo 'tipo' (que não existe mais).</p>";
        } else {
            echo "<p class='success'>✓ Encontrados " . count($usuarioRoles) . " relacionamento(s) usuário-role</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Usuario ID</th><th>Usuario Nome</th><th>Usuario Email</th><th>Role</th><th>Role Nome</th><th>Criado em</th></tr>";
            
            foreach ($usuarioRoles as $ur) {
                $rowClass = '';
                if (strtoupper($ur['role']) === 'INSTRUTOR') {
                    $rowClass = 'instrutor';
                } elseif (strtoupper($ur['role']) === 'ADMIN') {
                    $rowClass = 'admin';
                } elseif (strtoupper($ur['role']) === 'ALUNO') {
                    $rowClass = 'aluno';
                }
                
                echo "<tr class='{$rowClass}'>";
                echo "<td>{$ur['id']}</td>";
                echo "<td>{$ur['usuario_id']}</td>";
                echo "<td>" . htmlspecialchars($ur['usuario_nome'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($ur['usuario_email'] ?? 'N/A') . "</td>";
                echo "<td><strong>" . htmlspecialchars($ur['role']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($ur['role_nome'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($ur['created_at'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro ao consultar tabela 'usuario_roles': " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // 5. Usuários com role INSTRUTOR
    echo "<div class='info'>";
    echo "<h2>5. Usuários com Role 'INSTRUTOR'</h2>";
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nome, u.email, u.status, u.cpf, u.telefone, ur.role, ur.created_at as role_assigned_at
            FROM usuarios u
            INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
            WHERE ur.role = 'INSTRUTOR'
            ORDER BY u.nome
        ");
        $stmt->execute();
        $instrutores = $stmt->fetchAll();
        
        if (empty($instrutores)) {
            echo "<p class='warning'>⚠ Nenhum usuário com role 'INSTRUTOR' encontrado!</p>";
            echo "<p class='info'>Isso pode explicar por que o login de instrutor não funciona.</p>";
        } else {
            echo "<p class='success'>✓ Encontrados " . count($instrutores) . " instrutor(es)</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Status</th><th>CPF</th><th>Telefone</th><th>Role Atribuído em</th></tr>";
            
            foreach ($instrutores as $instr) {
                echo "<tr class='instrutor'>";
                echo "<td>{$instr['id']}</td>";
                echo "<td><strong>" . htmlspecialchars($instr['nome'] ?? 'N/A') . "</strong></td>";
                echo "<td>" . htmlspecialchars($instr['email'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($instr['status'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($instr['cpf'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($instr['telefone'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($instr['role_assigned_at'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Erro ao consultar instrutores: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // 6. Busca específica: rwavieira@gmail.com
    echo "<div class='info'>";
    echo "<h2>6. Busca Específica: rwavieira@gmail.com</h2>";
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute(['rwavieira@gmail.com']);
    $usuarioEspecifico = $stmt->fetch();
    
    if ($usuarioEspecifico) {
        echo "<p class='success'>✓ Usuário encontrado:</p>";
        echo "<table>";
        echo "<tr><th>Campo</th><th>Valor</th></tr>";
        foreach ($usuarioEspecifico as $key => $value) {
            if ($key === 'senha' || $key === 'password') {
                echo "<tr><td><strong>{$key}</strong></td><td>" . (empty($value) ? '<span class="error">VAZIA</span>' : '*** (hash presente, tamanho: ' . strlen($value) . ')') . "</td></tr>";
            } else {
                echo "<tr><td><strong>{$key}</strong></td><td>" . htmlspecialchars($value ?? 'N/A') . "</td></tr>";
            }
        }
        echo "</table>";
        
        // Verificar roles do usuário
        if (!empty($usuarioEspecifico['id'])) {
            echo "<h3>Roles/Papéis deste Usuário:</h3>";
            try {
                $stmt = $pdo->prepare("
                    SELECT ur.role, r.nome as role_nome, r.descricao as role_descricao, ur.created_at
                    FROM usuario_roles ur
                    LEFT JOIN roles r ON ur.role = r.role
                    WHERE ur.usuario_id = ?
                ");
                $stmt->execute([$usuarioEspecifico['id']]);
                $rolesUsuario = $stmt->fetchAll();
                
                if (empty($rolesUsuario)) {
                    echo "<p class='error'>✗ Este usuário NÃO tem nenhum role atribuído na tabela 'usuario_roles'!</p>";
                    echo "<p class='warning'>⚠ <strong>PROBLEMA IDENTIFICADO:</strong> O sistema tentará usar o campo 'tipo' (que não existe mais), causando falha no login.</p>";
                } else {
                    echo "<p class='success'>✓ Este usuário tem " . count($rolesUsuario) . " role(s) atribuído(s):</p>";
                    echo "<table>";
                    echo "<tr><th>Role</th><th>Nome</th><th>Descrição</th><th>Atribuído em</th></tr>";
                    foreach ($rolesUsuario as $role) {
                        $isInstrutor = (strtoupper($role['role']) === 'INSTRUTOR');
                        $rowClass = $isInstrutor ? 'instrutor' : '';
                        echo "<tr class='{$rowClass}'>";
                        echo "<td><strong>" . htmlspecialchars($role['role']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($role['role_nome'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($role['role_descricao'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($role['created_at'] ?? 'N/A') . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    
                    // Verificar se tem role INSTRUTOR
                    $temInstrutor = false;
                    foreach ($rolesUsuario as $role) {
                        if (strtoupper($role['role']) === 'INSTRUTOR') {
                            $temInstrutor = true;
                            break;
                        }
                    }
                    
                    if (!$temInstrutor) {
                        echo "<p class='error'>✗ <strong>PROBLEMA:</strong> Este usuário NÃO tem o role 'INSTRUTOR' atribuído!</p>";
                        echo "<p class='info'>Para corrigir, execute: <code>INSERT INTO usuario_roles (usuario_id, role) VALUES ({$usuarioEspecifico['id']}, 'INSTRUTOR');</code></p>";
                    } else {
                        echo "<p class='success'>✓ Este usuário TEM o role 'INSTRUTOR' atribuído.</p>";
                    }
                }
            } catch (PDOException $e) {
                echo "<p class='error'>✗ Erro ao verificar roles: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    } else {
        echo "<p class='error'>✗ Usuário não encontrado!</p>";
    }
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>ERRO de Conexão</h2>";
    echo "<p>✗ Erro ao conectar ao banco: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>ERRO</h2>";
    echo "<p>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "</div></body></html>";
?>
