<?php
// SCRIPT DE TESTE: VERIFICAR ESTRUTURA DO BANCO
// Executar para confirmar se tabela sessoes existe e estrutura das tabelas

require_once 'includes/config.php';
require_once 'includes/database.php';

echo "<h2>🔍 DIAGNÓSTICO DO BANCO DE DADOS</h2>";

try {
    $db = db();
    
    // 1. Verificar se tabela sessoes existe
    echo "<h3>1. Verificando tabela 'sessoes'</h3>";
    $checkSessoes = $db->fetch("SHOW TABLES LIKE 'sessoes'");
    
    if ($checkSessoes) {
        echo "✅ Tabela 'sessoes' EXISTE<br>";
        
        // Mostrar estrutura
        $structure = $db->fetchAll("DESCRIBE sessoes");
        echo "<pre>";
        print_r($structure);
        echo "</pre>";
    } else {
        echo "❌ Tabela 'sessoes' NÃO EXISTE<br>";
    }
    
    // 2. Verificar estrutura das tabelas de usuários
    echo "<h3>2. Verificando tabela 'usuarios'</h3>";
    $checkUsuarios = $db->fetch("SHOW TABLES LIKE 'usuarios'");
    
    if ($checkUsuarios) {
        echo "✅ Tabela 'usuarios' EXISTE<br>";
        
        // Verificar colunas importantes
        $columns = $db->fetchAll("DESCRIBE usuarios");
        $importantColumns = ['id', 'nome', 'email', 'cpf', 'senha', 'password', 'ativo', 'status', 'tipo'];
        
        echo "<table border='1'>";
        echo "<tr><th>Coluna</th><th>Tipo</th><th>Importante?</th></tr>";
        
        foreach ($columns as $col) {
            $isImportant = in_array($col['Field'], $importantColumns);
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>" . ($isImportant ? "✅" : "") . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Tabela 'usuarios' NÃO EXISTE<br>";
    }
    
    // 3. Verificar tabela alunos
    echo "<h3>3. Verificando tabela 'alunos'</h3>";
    $checkAlunos = $db->fetch("SHOW TABLES LIKE 'alunos'");
    
    if ($checkAlunos) {
        echo "✅ Tabela 'alunos' EXISTE<br>";
        
        $columns = $db->fetchAll("DESCRIBE alunos");
        echo "<table border='1'>";
        echo "<tr><th>Coluna</th><th>Tipo</th></tr>";
        
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Tabela 'alunos' NÃO EXISTE<br>";
    }
    
    // 4. Verificar se há alunos cadastrados
    echo "<h3>4. Verificando dados de alunos</h3>";
    
    try {
        $alunosUsuarios = $db->fetchAll("SELECT COUNT(*) as total FROM usuarios WHERE tipo = 'aluno' AND ativo = 1");
        echo "Alunos na tabela 'usuarios': " . $alunosUsuarios[0]['total'] . "<br>";
        
        $alunosTable = $db->fetchAll("SELECT COUNT(*) as total FROM alunos WHERE ativo = 1");
        echo "Alunos na tabela 'alunos': " . $alunosTable[0]['total'] . "<br>";
        
        // Mostrar exemplos
        $exemplos = $db->fetchAll("SELECT id, nome, email, cpf, ativo FROM usuarios WHERE tipo = 'aluno' LIMIT 3");
        echo "<h4>Exemplos de alunos em 'usuarios':</h4>";
        echo "<pre>";
        print_r($exemplos);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "❌ Erro ao consultar dados: " . $e->getMessage() . "<br>";
    }
    
    // 5. Verificar configuração de sessão
    echo "<h3>5. Configuração de Sessão</h3>";
    echo "SESSION_TIMEOUT: " . SESSION_TIMEOUT . " segundos (" . (SESSION_TIMEOUT/60) . " minutos)<br>";
    echo "SESSION_NAME: " . SESSION_NAME . "<br>";
    echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "<br>";
    echo "session.gc_maxlifetime: " . ini_get('session.gc_maxlifetime') . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erro geral: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

?>
