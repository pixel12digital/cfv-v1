<?php
// SCRIPT DE TESTE: VERIFICAR USUÁRIOS COM CPF E SENHA
// Para encontrar alunos reais para teste

require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h2>🔍 BUSCANDO ALUNOS REAIS PARA TESTE</h2>";

try {
    $db = db();
    
    // 1. Verificar usuários que têm CPF
    echo "<h3>1. Usuários com CPF preenchido</h3>";
    $usuariosComCpf = $db->fetchAll("SELECT id, nome, email, cpf, password, status FROM usuarios WHERE cpf IS NOT NULL AND cpf != '' AND status = 'ativo'");
    
    echo "Encontrados: " . count($usuariosComCpf) . " usuários com CPF<br>";
    
    if (!empty($usuariosComCpf)) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>CPF</th><th>Tem Senha</th></tr>";
        
        foreach ($usuariosComCpf as $usuario) {
            echo "<tr>";
            echo "<td>{$usuario['id']}</td>";
            echo "<td>{$usuario['nome']}</td>";
            echo "<td>{$usuario['email']}</td>";
            echo "<td>{$usuario['cpf']}</td>";
            echo "<td>" . ($usuario['password'] ? "✅" : "❌") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Testar senha do primeiro usuário com CPF
        $primeiroUsuario = $usuariosComCpf[0];
        echo "<h3>2. Testando senha do primeiro usuário com CPF</h3>";
        echo "Usuário: {$primeiroUsuario['nome']}<br>";
        echo "CPF: {$primeiroUsuario['cpf']}<br>";
        
        if ($primeiroUsuario['password']) {
            $senhaPadrao = '123456';
            $senhaValida = password_verify($senhaPadrao, $primeiroUsuario['password']);
            
            echo "Senha padrão (123456) válida: " . ($senhaValida ? "✅ SIM" : "❌ NÃO") . "<br>";
            echo "Hash: " . substr($primeiroUsuario['password'], 0, 20) . "...<br>";
        }
    }
    
    // 2. Verificar se há tabela usuario_roles (RBAC)
    echo "<h3>3. Verificando sistema RBAC</h3>";
    try {
        $checkRoles = $db->fetch("SHOW TABLES LIKE 'usuario_roles'");
        if ($checkRoles) {
            echo "✅ Tabela 'usuario_roles' existe<br>";
            
            $roles = $db->fetchAll("SELECT ur.usuario_id, ur.role, u.nome FROM usuario_roles ur JOIN usuarios u ON ur.usuario_id = u.id LIMIT 10");
            echo "<table border='1'>";
            echo "<tr><th>Usuário</th><th>Role</th></tr>";
            
            foreach ($roles as $role) {
                echo "<tr>";
                echo "<td>{$role['nome']}</td>";
                echo "<td>{$role['role']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "❌ Tabela 'usuario_roles' não existe<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erro ao verificar RBAC: " . $e->getMessage() . "<br>";
    }
    
    // 3. Verificar todos os usuários ativos
    echo "<h3>4. Todos os usuários ativos</h3>";
    $todosUsuarios = $db->fetchAll("SELECT id, nome, email, cpf, password, status FROM usuarios WHERE status = 'ativo' ORDER BY id");
    
    echo "Total de usuários ativos: " . count($todosUsuarios) . "<br>";
    
    // Verificar quais têm senha válida
    $comSenhaValida = 0;
    foreach ($todosUsuarios as $usuario) {
        if ($usuario['password'] && password_verify('123456', $usuario['password'])) {
            $comSenhaValida++;
        }
    }
    
    echo "Usuários com senha padrão (123456): $comSenhaValida<br>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

?>
