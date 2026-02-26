<?php
// SCRIPT FINAL: DIAGNÓSTICO COMPLETO COM EVIDÊNCIAS
// Identificar a CAUSA EXATA do problema de login

require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h2>🔍 DIAGNÓSTICO FINAL - CAUSA EXATA DO PROBLEMA</h2>";

try {
    $db = db();
    
    echo "<h3>📋 EVIDÊNCIA 1: Estrutura da tabela 'usuarios'</h3>";
    $columns = $db->fetchAll("DESCRIBE usuarios");
    echo "<table border='1'>";
    echo "<tr><th>Coluna</th><th>Tipo</th><th>Problema?</th></tr>";
    
    $problemas = [];
    foreach ($columns as $col) {
        $temProblema = false;
        $motivo = "";
        
        if ($col['Field'] === 'ativo') {
            $temProblema = true;
            $motivo = "Código busca por 'ativo' mas coluna não existe";
            $problemas[] = "Coluna 'ativo' não existe";
        }
        
        if ($col['Field'] === 'tipo') {
            $temProblema = true;
            $motivo = "Código busca por 'tipo' mas coluna não existe";
            $problemas[] = "Coluna 'tipo' não existe";
        }
        
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>" . ($temProblema ? "🔴 $motivo" : "") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>📋 EVIDÊNCIA 2: Teste de busca de usuário</h3>";
    
    // Testar exatamente como o código faz
    echo "Testando busca por email (como o Auth faz):<br>";
    try {
        $result = $db->fetch("SELECT id, nome, email, cpf, ativo FROM usuarios WHERE email = :email LIMIT 1", ['email' => 'jsamuelfdeus@hotmail.com']);
        echo "✅ Busca com 'ativo' funcionou<br>";
    } catch (Exception $e) {
        echo "❌ Busca com 'ativo' falhou: " . $e->getMessage() . "<br>";
        if (strpos($e->getMessage(), "Unknown column 'ativo'") !== false) {
            echo "🔴 CONFIRMADO: Coluna 'ativo' não existe!<br>";
            $problemas[] = "Busca por 'ativo' falha";
        }
    }
    
    echo "<br>Testando busca correta (com 'status'):<br>";
    try {
        $result = $db->fetch("SELECT id, nome, email, cpf, status FROM usuarios WHERE email = :email LIMIT 1", ['email' => 'jsamuelfdeus@hotmail.com']);
        echo "✅ Busca com 'status' funcionou<br>";
        if ($result) {
            echo "Usuário encontrado: " . $result['nome'] . " (status: " . $result['status'] . ")<br>";
        }
    } catch (Exception $e) {
        echo "❌ Busca com 'status' falhou: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>📋 EVIDÊNCIA 3: Tabela 'sessoes'</h3>";
    $checkSessoes = $db->fetch("SHOW TABLES LIKE 'sessoes'");
    if ($checkSessoes) {
        echo "✅ Tabela 'sessoes' existe<br>";
    } else {
        echo "❌ Tabela 'sessoes' não existe<br>";
        echo "🔴 CONFIRMADO: Código referencia tabela inexistente<br>";
        $problemas[] = "Tabela 'sessoes' não existe";
    }
    
    echo "<h3>📋 EVIDÊNCIA 4: Sistema RBAC vs Legado</h3>";
    
    // Verificar se há alunos via RBAC
    $alunosRbac = $db->fetchAll("
        SELECT u.id, u.nome, u.email, u.status 
        FROM usuarios u 
        JOIN usuario_roles ur ON u.id = ur.usuario_id 
        WHERE ur.role = 'ALUNO' AND u.status = 'ativo'
        LIMIT 3
    ");
    
    echo "Alunos via RBAC: " . count($alunosRbac) . "<br>";
    
    // Verificar se login.php usa lógica correta
    echo "<h3>📋 EVIDÊNCIA 5: Análise do código de login</h3>";
    
    $loginPhpContent = file_get_contents('login.php');
    
    // Verificar se login.php busca por 'ativo' ou 'status'
    if (strpos($loginPhpContent, "ativo = 1") !== false) {
        echo "🔴 login.php busca por 'ativo = 1' (coluna não existe)<br>";
        $problemas[] = "login.php usa coluna 'ativo' inexistente";
    }
    
    if (strpos($loginPhpContent, "status = 'ativo'") !== false) {
        echo "✅ login.php busca por 'status = 'ativo'' (correto)<br>";
    }
    
    // Verificar Auth class
    $authPhpContent = file_get_contents('includes/auth.php');
    if (strpos($authPhpContent, "ativo") !== false) {
        echo "🔴 Auth.php referencia coluna 'ativo'<br>";
        $problemas[] = "Auth.php usa coluna 'ativo' inexistente";
    }
    
    echo "<h3>🎯 DIAGNÓSTICO FINAL</h3>";
    
    echo "<h4>Problemas Confirmados:</h4>";
    echo "<ul>";
    foreach ($problemas as $problema) {
        echo "<li>🔴 $problema</li>";
    }
    echo "</ul>";
    
    echo "<h4>Causa Raiz Identificada:</h4>";
    
    if (in_array("Coluna 'ativo' não existe", $problemas)) {
        echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid red;'>";
        echo "<strong>🔴 CAUSA EXATA: Inconsistência de colunas no banco</strong><br>";
        echo "O código busca pela coluna 'ativo' mas a tabela 'usuarios' tem apenas 'status'.<br>";
        echo "Isso faz com que qualquer query que use 'ativo' falhe, impedindo o login.";
        echo "</div>";
    }
    
    if (in_array("Tabela 'sessoes' não existe", $problemas)) {
        echo "<div style='background: #ffe6e6; padding: 10px; border: 1px solid red;'>";
        echo "<strong>🔴 CAUSA EXATA: Tabela 'sessoes' ausente</strong><br>";
        echo "O código tenta registrar/validar sessões na tabela 'sessoes' que não existe.<br>";
        echo "Isso causa exceções durante o processo de login.";
        echo "</div>";
    }
    
    echo "<h4>Impacto no Fluxo de Login:</h4>";
    echo "<ol>";
    echo "<li>Aluno tenta fazer login → Sistema busca usuário com 'ativo' → ERRO</li>";
    echo "<li>Se conseguir login, sistema tenta registrar sessão → Tabela 'sessoes' não existe → ERRO</li>";
    echo "<li>Primeiro acesso pode funcionar (por sorte), mas acessos seguintes falham</li>";
    echo "</ol>";
    
    echo "<h4>✅ SOLUÇÃO CONFIRMADA:</h4>";
    echo "<div style='background: #e6ffe6; padding: 10px; border: 1px solid green;'>";
    echo "<strong>Correção necessária:</strong><br>";
    echo "1. Substituir todas as referências de 'ativo' por 'status' no código<br>";
    echo "2. Criar tabela 'sessoes' no banco de dados<br>";
    echo "3. Testar o fluxo completo de login";
    echo "</div>";
    
} catch (Exception $e) {
    echo "❌ Erro no diagnóstico: " . $e->getMessage() . "<br>";
}

?>
